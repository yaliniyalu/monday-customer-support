<?php

namespace App\Domain\Chat\Provider;

use App\Domain\Chat\Attachment;
use App\Domain\Chat\Message;
use App\Domain\Chat\User;
use Base64Url\Base64Url;
use GuzzleHttp\Psr7\Stream;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

class Outlook implements Provider
{
    private Graph $graph;

    private string $mailFields = "id,conversationId,subject,createdDateTime,receivedDateTime,isRead,isDraft,body," .
    "from,toRecipients,hasAttachments";

    public function __construct(Graph $graph)
    {
        $this->graph = $graph;
    }

    private function queryMails($select, $filter, $orderBy, $top): array
    {
        $mails = $this->graph->createRequest(
            'GET',
            '/me/messages?$select=' . $select . '&$top=' . $top . '&$filter=' . $filter . '&$orderby=' . $orderBy
        )
            ->setReturnType(Model\Message::class)
            ->execute();

        $m = '/me/messages?$select=' . $select . '&$top=' . $top . '&$filter=' . $filter . '&$orderby=' . $orderBy;
//Prefer: outlook.body-content-type="text"

        return $this->getMessageFromMail($mails);
    }

    /**
     * @inheritDoc
     */
    public function getAllMessagesByChat(string $chatId): array
    {
        $orderBy = "createdDateTime desc";
        $filter = "createdDateTime gt 1977-01-01T00:00:00Z and conversationId eq '{$chatId}' and isDraft eq false";

        $mails = $this->queryMails($this->mailFields, $filter, $orderBy, 50);
        $mails = array_reverse($mails, false);

        return $mails;
    }

    /**
     * @inheritDoc
     */
    public function getUnreadMessagesByChat(string $chatId): array
    {
        $orderBy = "createdDateTime desc";
        $filter = "createdDateTime gt 1977-01-01T00:00:00Z and conversationId eq '{$chatId}'" .
            " and isDraft eq false and isRead eq false";

        $mails = $this->queryMails($this->mailFields, $filter, $orderBy, 50);
        $mails = array_reverse($mails, false);

        return $mails;
    }

    /**
     * @inheritDoc
     */
    public function getMessagesFromNotification(array $notification, ?string $processedId): array
    {
        if (!$processedId) {
            $processedId = time() - 300;
        }

        $dt = new \DateTime();
        $dt->setTimestamp($processedId)->setTimezone(new \DateTimeZone("utc"));
        $time = $dt->format("Y-m-d\TH:i:s\Z");

        $orderBy = "createdDateTime asc";
        $filter = "createdDateTime gt {$time} and isDraft eq false";

        $mails = $this->queryMails($this->mailFields, $filter, $orderBy, 50);
        $mails = array_reverse($mails, false);

        return $mails;
    }

    public function getMessage(string $messageId): Message
    {
        $filter = "id eq '{$messageId}'";

        $mails = $this->queryMails($this->mailFields, $filter, "", 1);
        $mails = array_reverse($mails, false);

        if (!count($mails)) {
            throw new \Exception("message not found");
        }

        return $mails[0];
    }

    public function getAttachment(string $messageId, string $attachmentId): Attachment
    {
        /** @var Model\FileAttachment $data */
        $data = $this->graph
            ->createRequest('GET', "/me/messages/{$messageId}/attachments/{$attachmentId}")
            ->setReturnType(Model\FileAttachment::class)
            ->execute();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, Base64Url::decode($data->getContentBytes()));
        rewind($stream);

        $attachment = new Attachment($data->getId(), $data->getName(), $data->getContentType(), "");
        $attachment->setData(new Stream($stream));
        return $attachment;
    }

    public function subscribe(): string
    {
        $dateTime = new \DateTime();
        $dateTime->add(new \DateInterval('P2DT20H'));

        $subscription = new Model\Subscription();
        $subscription->setChangeType("created");
        $subscription->setNotificationUrl($_ENV['OUTLOOK_NOTIFICATION_URL']);
        $subscription->setResource("me/mailFolders('Inbox')/messages");
        $subscription->setExpirationDateTime($dateTime);

        /** @var Model\Subscription $subscription */
        $subscription = $this->graph
            ->createRequest('POST', '/subscriptions')
            ->attachBody($subscription)
            ->setReturnType(Model\Subscription::class)
            ->execute();

        return $subscription->getId();
    }

    public function unsubscribe(string $subscriptionId): void
    {
        $this->graph
            ->createRequest('DELETE', "/subscriptions/{$subscriptionId}")
            ->execute();
    }

    public function renewSubscription(string $subscriptionId): void
    {
        $dateTime = new \DateTime();
        $dateTime->add(new \DateInterval('2DT20H'));

        $subscription = new Model\Subscription();
        $subscription->setExpirationDateTime($dateTime);

        $this->graph
            ->createRequest('PATCH', "/subscriptions/{$subscriptionId}")
            ->attachBody($subscription)
            ->setReturnType(Model\Subscription::class)
            ->execute();
    }

    public function getInitialSubscriptionState(): ?string
    {
        return time();
    }

    public function reauthorizeSubscription(string $subscriptionId): void
    {
        $this->graph
            ->createRequest('POST', "/subscriptions/{$subscriptionId}/reauthorize")
            ->execute();
    }

    /**
     * @inheritDoc
     */
    public function send(User $from, string $toId, string $message, string $chatId = null): Message
    {
        $outlookMessage = new Model\Message();
        $emailAddress = new Model\EmailAddress();
        $emailAddress->setAddress($toId);

        $toRecipient = new Model\Recipient();
        $toRecipient->setEmailAddress($emailAddress);

        $body = new Model\ItemBody();
        $body->setContent($message);
        $body->setContentType(new Model\BodyType(Model\BodyType::HTML));

        $outlookMessage->setBody($body);
        $outlookMessage->setIsDraft(true);

        if ($chatId) {
            $lastMessage = $this->getLastMessageOfChatFromSender($chatId, $toId);
            $subject = $lastMessage->getSubject();

            /** @var Model\Message $outlookMessage */
            $outlookMessage = $this->graph
                ->createRequest('POST', "/me/messages/{$lastMessage->getId()}/createReply")
                ->setReturnType(Model\Message::class)
                ->execute();

            $updatedMessage = $message . "\n" . $outlookMessage->getBody()->getContent();
            $body->setContent($updatedMessage);
            $outlookMessage->setBody($body);

            $outlookMessage = $this->graph
                ->createRequest("PATCH", "/me/messages/{$outlookMessage->getId()}")
                ->attachBody($outlookMessage)
                ->setReturnType(Model\Message::class)
                ->execute();

        } else {
            $outlookMessage->setToRecipients([$toRecipient]);
            $endpoint = "/me/messages";
            $subject = "";

            /** @var Model\Message $outlookMessage */
            $outlookMessage = $this->graph
                ->createRequest('POST', $endpoint)
                ->addHeaders(['Prefer', 'outlook.body-content-type="text"'])
                ->attachBody($outlookMessage)
                ->setReturnType(Model\Message::class)
                ->execute();
        }

        $res = new Model\Recipient();
        $res->setEmailAddress(new Model\EmailAddress(['address' => $from->getId(), 'name' => $from->getName()]));
        $outlookMessage->setFrom($res);
        $outlookMessage->setSubject($subject);

        $this->graph
            ->createRequest('POST', "/me/messages/{$outlookMessage->getId()}/send")
            ->execute();

        return $this->getMessageFromMail([$outlookMessage])[0];
    }

    public function markAsRead(array $messageIds): array
    {
        $requests = [];
        foreach ($messageIds as $messageId) {
            $requests[] = [
                'id' => $messageId,
                'method' => "PATCH",
                'url' => "/me/messages/{$messageId}",
                'body' => [
                    "isRead" => true
                ],
                'headers' => [
                    'Content-Type' => "application/json"
                ]
            ];
        }

        $responses = $this->graph->createRequest("POST", '/$batch')
            ->attachBody(['requests' => $requests])
            ->execute();

        $responses = json_decode((string) $responses->getRawBody(), true);

        $marked = [];
        foreach ($responses['responses'] as $response) {
            if ($response['status'] == 200) {
                $marked[] = $response['id'];
            }
        }

        return $marked;
    }

    public function addLabel(string $chatId, string $label): void
    {
        // TODO: Implement addLabel() method.
    }

    public function setLabel(string $chatId, string $label, array $removeLabels): void
    {
        // TODO: Implement setLabel() method.
    }

    public function getAccountUserInfo(): User
    {
        $user = $this->graph->createRequest('GET', '/me?$select=displayName,mail,userPrincipalName')
            ->setReturnType(Model\User::class)
            ->execute();

        $email = null !== $user->getMail() ? $user->getMail() : $user->getUserPrincipalName();
        $name = $user->getDisplayName();
        return new User($email, $name);
    }

    public function getLastMessageOfChat(string $chatId): Message
    {
        $orderBy = "createdDateTime desc";
        $filter = "createdDateTime gt 1977-01-01T00:00:00Z and conversationId eq '{$chatId}' and isDraft eq false";

        $mails = $this->queryMails($this->mailFields, $filter, $orderBy, 1);
        $mails = array_reverse($mails, false);

        return $mails[0];
    }

    public function getLastMessageOfChatFromSender(string $chatId, string $sender): Message
    {
        $orderBy = "createdDateTime desc";
        $filter = "createdDateTime gt 1977-01-01T00:00:00Z and conversationId eq '{$chatId}' and isDraft eq false";
        $filter .= "  and from/emailAddress/address eq '{$sender}'";

        $mails = $this->queryMails($this->mailFields, $filter, $orderBy, 1);
        $mails = array_reverse($mails, false);

        return $mails[0];
    }

    /**
     * @param string $messageId
     * @return Attachment[]
     * @throws \Microsoft\Graph\Exception\GraphException
     */
    private function getAttachments(string $messageId): array
    {
        $select = "id,name,contentType";
        /** @var Model\Attachment[] $attachments */
        $attachments = $this->graph
            ->createRequest('GET', "/me/messages/{$messageId}/attachments" . '?$select=' . $select)
            ->setReturnType(Model\Attachment::class)
            ->execute();

        $results = [];
        foreach ($attachments as $attachment) {
            if ($attachment->getODataType() != "#microsoft.graph.fileAttachment") {
                continue;
            }

            $results[] = new Attachment(
                $attachment->getId(),
                $attachment->getName(),
                $attachment->getContentType(),
                ""
            );
        }

        return $results;
    }

    /**
     * @param Model\Message[] $mails
     * @return Message[]
     */
    private function getMessageFromMail(array $mails): array
    {
        $messages = [];
        foreach ($mails as $mail) {
            $from = $mail->getFrom()->getEmailAddress();
            $toRecipient = $mail->getToRecipients()[0];

            if (!$toRecipient instanceof Model\Recipient) {
                $to = $toRecipient['emailAddress'];
                $to = new Model\EmailAddress(['address' => $to['address'], 'name' => $to['name']]);
            } else {
                $to = $toRecipient->getEmailAddress();
            }

            $message = new Message(
                $mail->getId(),
                $mail->getConversationId(),
                new User($from->getAddress(), $from->getName()),
                new User($to->getAddress(), $to->getName() ?? "")
            );

            $message->setIsRead($mail->getIsRead());
            $body = $mail->getBody();

            if ($body->getContentType() == 'text') {
                $message->setText($body->getContent());
            } else {
                $message->setHtml($body->getContent());
            }

            $message->setDate($mail->getCreatedDateTime()->format('Y-m-d H:i:sP'));
            $message->setIndex($mail->getCreatedDateTime()->getTimestamp());
            $message->setSubject($mail->getSubject());

            if ($mail->getHasAttachments()) {
                $message->setAttachments($this->getAttachments($mail->getId()));
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
