<?php

namespace App\Domain\Chat\Provider;

use App\Domain\Chat\Attachment;
use App\Domain\Chat\Message;
use App\Domain\Chat\User;
use Base64Url\Base64Url;
use EmailReplyParser\Parser\EmailParser;
use Exception;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_BatchModifyMessagesRequest;
use Google_Service_Gmail_ListHistoryResponse;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_MessagePart;
use Google_Service_Gmail_MessagePartHeader;
use Google_Service_Gmail_ModifyMessageRequest;
use Google_Service_Gmail_ModifyThreadRequest;
use Google_Service_Gmail_Thread;
use Google_Service_Gmail_WatchRequest;
use GuzzleHttp\Psr7\Stream;

class Gmail implements Provider
{
    private Google_Client $client;

    public function __construct(Google_Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $chatId
     * @return Message[]
     */
    public function getAllMessagesByChat(string $chatId): array
    {
        $gmail = new Google_Service_Gmail($this->client);
        $thread = $gmail->users_threads->get('me', $chatId);
        return self::messagesFromThread($thread);
    }

    /**
     * @param string $chatId
     * @return Message[]
     */
    public function getUnreadMessagesByChat(string $chatId): array
    {
        $messages = $this->getAllMessagesByChat($chatId);
        return array_filter($messages, fn($m) => !$m->isRead());
    }

    public function getMessage(string $messageId): Message
    {
        $gmail = new Google_Service_Gmail($this->client);
        $messages = $gmail->users_messages->get('me', $messageId);
        return self::messagesFromMessages([$messages])[0];
    }

    public function getAttachment(string $messageId, string $attachmentId): Attachment
    {
        $att = json_decode(Base64Url::decode($attachmentId), true);
        $gmail = new Google_Service_Gmail($this->client);
        $body = $gmail->users_messages_attachments->get('me', $messageId, $att['id']);
        $data = Base64Url::decode($body->getData());
        $attachment = new Attachment($attachmentId, $att['name'], $att['type']);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $data);
        rewind($stream);
        $attachment->setData(new Stream($stream));
        return $attachment;
    }

    public function subscribe(): string
    {
        $gmail = new Google_Service_Gmail($this->client);
        $postBody = new Google_Service_Gmail_WatchRequest();
        $postBody->setTopicName($_ENV['GMAIL_SUB_TOPIC']);
        $gmail->users->watch("me", $postBody);
        return "";
    }

    public function unsubscribe(string $subscriptionId): void
    {
        $gmail = new Google_Service_Gmail($this->client);
        $gmail->users->stop("me");
    }

    public function renewSubscription(string $subscriptionId): void
    {
        // Not Supported
    }

    public function getInitialSubscriptionState(): ?string
    {
        return null;
    }

    public function send(User $from, string $toId, string $message, string $chatId = null): Message
    {
        $gmail = new Google_Service_Gmail($this->client);
        $msg = new Google_Service_Gmail_Message();

        if ($chatId) {
            $msg->setThreadId($chatId);

            $thread = $gmail->users_threads->get('me', $chatId);
            $threadMessages = self::messagesFromThread($thread);
            $threadMessage = $threadMessages[0];
            $subject = $threadMessage->getSubject();

            if ($threadMessage->getTo()->getId() === $from->getId()) {
                $to = $threadMessage->getFrom();
            } else {
                $to = $threadMessage->getTo();
            }

            if ($to->getId() != $toId) {
                $to = new User($toId);
            }

            //get last
            $f = null;
            for ($i = count($threadMessages) - 1; $i >= 0; $i--) {
                if ($threadMessages[$i]->getFrom()->getId() == $toId) {
                    $f = $i;
                    break;
                }
            }

            $references = null;
            $messageId = null;

            if ($f !== null) {
                /** @var $rawMessage Google_Service_Gmail_Message */
                $rawMessage = $thread[$f];
                $payload = $rawMessage->getPayload();

                foreach ($payload->getHeaders() as $header) { /** @var $header Google_Service_Gmail_MessagePartHeader */
                    if ($header->getName() == 'References') {
                        $references = $header->getValue();
                    } elseif ($header->getName() == 'Message-ID') {
                        $messageId = $header->getValue();
                    }
                }
            }
        } else {
            $subject = "Customer Support";
            $to = new User($toId);
            $references = null;
            $messageId = null;
        }

        $messageParts = [];
        $messageParts[] = "From: {$from->toString()}";
        $messageParts[] = "To: {$to->toString()}";
        $messageParts[] = "Subject: {$subject}";
        $messageParts[] = "Content-Type: text/html; charset=UTF-8";

        if ($references) {
            $messageParts[] = "References: {$references}";
        }

        if ($messageId) {
            $messageParts[] = "In-Reply-To: {$messageId}";
        }

        $messageParts = implode("\n", $messageParts);

        $messageRaw = "{$messageParts}

$message";

        $msg->setRaw(Base64Url::encode($messageRaw));
        $message = $gmail->users_messages->send('me', $msg);
        $messages = $gmail->users_messages->get('me', $message->getId());

        return self::messagesFromMessages([$messages])[0];
    }

    /**
     * @param string $userId
     * @return Message[]
     */
    public function getAllMessagesByUser(string $userId): array
    {
        $gmail = new Google_Service_Gmail($this->client);
        $messages = $gmail->users_messages->listUsersMessages('me', [
            'q' =>  "from:{$userId}",
            'maxResults' => 100
        ]);
        return self::messagesFromMessages($messages->getMessages());
    }

    /**
     * @param string $userId
     * @return Message[]
     */
    public function getUnreadMessagesByUser(string $userId): array
    {
        $gmail = new Google_Service_Gmail($this->client);
        $messages = $gmail->users_messages->listUsersMessages('me', [
            'q' =>  "from:{$userId} is:unread"
        ]);
        return self::messagesFromMessages($messages->getMessages());
    }

    /**
     * @param string[] $messageIds
     * @return string[]
     */
    public function markAsRead(array $messageIds): array
    {
        $gmail = new Google_Service_Gmail($this->client);
        $body = new Google_Service_Gmail_ModifyMessageRequest();
        $body->setRemoveLabelIds('UNREAD');

        $request = new Google_Service_Gmail_BatchModifyMessagesRequest();
        $request->setRemoveLabelIds(['UNREAD']);
        $request->setIds($messageIds);

        try {
            $gmail->users_messages->batchModify('me', $request);
            $marked = $messageIds;
        } catch (Exception $e) {
            $marked = [];
        }

        return $marked;
    }

    /**
     * @return \Google_Service_Gmail_Label[]
     */
    private function getAllLabels(): array
    {
        $gmail = new Google_Service_Gmail($this->client);
        return $gmail->users_labels->listUsersLabels('me')->getLabels();
    }

    private function getLabelId(string $labelName): ?string
    {
        $allLabels = $this->getAllLabels();
        foreach ($allLabels as $label) { /** @var $label \Google_Service_Gmail_Label */
            if ($label->getName() == $labelName) {
                return $label->getId();
            }
        }
        return null;
    }

    public function setLabel(string $chatId, string $labelName, array $removeLabels): void
    {
        $allLabels = $this->getAllLabels();
        $removeLabelIds = [];

        $labelId = null;
        foreach ($allLabels as $label) { /** @var $label \Google_Service_Gmail_Label */
            if ($label->getName() == $labelName) {
                $labelId = $label->getId();
            } elseif (in_array($label->getName(), $removeLabels)) {
                $removeLabelIds[] = $label->getId();
            }
        }

        if (!$labelId) {
            $labelId = $this->createLabel($labelName)->getId();
        }

        $gmail = new Google_Service_Gmail($this->client);
        $body = new Google_Service_Gmail_ModifyThreadRequest();
        $body->setAddLabelIds([$labelId]);
        $body->setRemoveLabelIds($removeLabelIds);

        $gmail->users_threads->modify('me', $chatId, $body);
    }

    public function addLabel(string $chatId, string $labelName): void
    {
        $labelId = $this->getLabelId($labelName);
        if (!$labelId) {
            $labelId = $this->createLabel($labelName)->getId();
        }

        $gmail = new Google_Service_Gmail($this->client);
        $body = new Google_Service_Gmail_ModifyThreadRequest();
        $body->setAddLabelIds([$labelId]);

        $gmail->users_threads->modify('me', $chatId, $body);
    }

    private function createLabel(string $labelName): \Google_Service_Gmail_Label
    {
        $gmail = new Google_Service_Gmail($this->client);
        $labelBody = new \Google_Service_Gmail_Label();
        $labelBody->setMessageListVisibility('SHOW');
        $labelBody->setLabelListVisibility('LABEL_SHOW');
        $labelBody->setName($labelName);
        return $gmail->users_labels->create('me', $labelBody);
    }

    public function getMessagesFromNotification(array $notification, ?string $processedId): array
    {
        if (!$processedId) {
            $processedId = $this->getLastHistoryId();
        }

        $history = $this->listHistory($processedId)->getHistory();
        $messageIds = [];
        foreach ($history as $item) { /** @var \Google_Service_Gmail_History $item */
            $addedMessages = $item->getMessagesAdded();

            foreach ($addedMessages as $addedMessage) {
                /** @var \Google_Service_Gmail_HistoryMessageAdded $addedMessage */
                $messageIds[] = $addedMessage->getMessage()->getId();
            }
        }

        if (!count($messageIds)) {
            return [];
        }

        return $this->getMessagesByIds($messageIds);
    }

    public function getLastHistoryId()
    {
        $gmail = new Google_Service_Gmail($this->client);
        $messages = $gmail->users_messages->listUsersMessages('me', ['maxResults' => 1]);

        /** @var $message Google_Service_Gmail_Message */
        $message = $messages->getMessages()[0];
        $msg = $this->getMessage($message->getId());
        return $msg->getAttribute('historyId');
    }

    public function listHistory(string $from = null): Google_Service_Gmail_ListHistoryResponse
    {
        $gmail = new Google_Service_Gmail($this->client);

        /** @var Google_Service_Gmail_ListHistoryResponse $history */
        $history = $gmail->users_history->listUsersHistory('me', [
            "maxResults" => 50,
            "startHistoryId" => $from,
            "historyTypes" => "messageAdded"
        ]);

        return $history;
    }

    public function getMessagesByIds(array $ids): array
    {
        $gmail = new Google_Service_Gmail($this->client);
        $this->client->setUseBatch(true);

        $batch = $gmail->createBatch();
        foreach ($ids as $id) {
            $get = $gmail->users_messages->get('me', $id);
            $batch->add($get);
        }
        $result = $batch->execute();

        $this->client->setUseBatch(false);

        return self::messagesFromMessages($result);
    }

    private static function messagesFromThread(Google_Service_Gmail_Thread $thread): array
    {
        $messages = $thread->getMessages();
        return self::messagesFromMessages($messages);
    }

    /**
     * @param Google_Service_Gmail_Message[] $messages
     * @return Message[]
     */
    private static function messagesFromMessages(array $messages): array
    {
        $chatMessages = [];
        foreach ($messages as $message) { /** @var $message Google_Service_Gmail_Message */
            $payload = $message->getPayload();

            $msg = new Message($message->getId(), $message->getThreadId(), null, null);

            foreach ($payload->getHeaders() as $header) { /** @var $header Google_Service_Gmail_MessagePartHeader */
                switch ($header->getName()) {
                    case 'From':
                        $msg->setFrom(self::getChatUserFromEmailHeader($header));
                        break;

                    case 'To':
                        $msg->setTo(self::getChatUserFromEmailHeader($header));
                        break;

                    case 'Date':
                        $msg->setDate(date('Y-m-d H:i:sP', strtotime($header->getValue())));
                        break;

                    case 'Subject':
                        $msg->setSubject($header->getValue());
                        break;
                }
            }

            $msg->setIsRead(!in_array('UNREAD', $message->getLabelIds()));
            $msg->setIndex((int) $message->historyId);
            $msg->setAttribute('historyId', $message->historyId);

            self::setChatMessageDetailsFromPart($msg, [$payload]);
            self::setChatMessageDetailsFromPart($msg, $payload->getParts());

            $chatMessages[] = $msg;
        }

        return $chatMessages;
    }

    private static function getChatUserFromEmailHeader(Google_Service_Gmail_MessagePartHeader $header)
    {
        $val = $header->getValue();

        if (mb_strpos($val, '<') !== false) {
            $arr = explode('<', $val);
            return new User(trim($arr[1], '>'), trim($arr[0]));
        } else {
            return new User($val);
        }
    }

    private static function setChatMessageDetailsFromPart(Message $message, array $parts)
    {
        foreach ($parts as $part) { /** @var $part Google_Service_Gmail_MessagePart */
            if ($part->mimeType == 'text/plain') {
                $text = Base64Url::decode($part->getBody()->data);
                $text = (new EmailParser())->parse($text);
                $message->setText($text->getVisibleText());
            } elseif ($part->mimeType == 'text/html') {
                $message->setHtml(Base64Url::decode($part->getBody()->data));
            } elseif ($part->mimeType == 'multipart/alternative') {
                self::setChatMessageDetailsFromPart($message, $part->getParts());
            } else {
                $att = [
                    'id' => $part->getBody()->attachmentId,
                    'name' => $part->filename,
                    'type' => $part->mimeType
                ];
                $attachmentId = Base64Url::encode(json_encode($att));
                $message->addAttachment(new Attachment($attachmentId, $part->filename, $part->mimeType));
            }
        }
    }

    public function getAccountUserInfo(): User
    {
        $google_service = new \Google_Service_Oauth2($this->client);
        $user = $google_service->userinfo->get();

        return new User($user->getEmail(), $user->getName());
    }
}
