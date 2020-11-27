<?php

namespace App\Domain\Chat\Provider;

use App\Domain\Chat\Attachment;
use App\Domain\Chat\Message;
use App\Domain\Chat\User;

interface Provider
{
    /**
     * @param string $chatId
     * @return Message[]
     */
    public function getAllMessagesByChat(string $chatId): array;

    /**
     * @param string $chatId
     * @return Message[]
     */
    public function getUnreadMessagesByChat(string $chatId): array;

    /**
     * @param array $notification
     * @return Message[]
     */
    public function getMessagesFromNotification(array $notification, ?string $processedId): array;
    public function getMessage(string $messageId): Message;
    public function getAttachment(string $messageId, string $attachmentId): Attachment;
    public function subscribe(): string;
    public function unsubscribe(string $subscriptionId): void;
    public function renewSubscription(string $subscriptionId): void;
    public function getInitialSubscriptionState(): ?string;

    /**
     * @param User $from
     * @param string $toId
     * @param string $message
     * @param string|null $chatId
     * @return Message[]
     */
    public function send(User $from, string $toId, string $message, string $chatId = null): Message;
    public function markAsRead(array $messageIds): array;
    public function addLabel(string $chatId, string $label): void;
    public function setLabel(string $chatId, string $label, array $removeLabels): void;
    public function getAccountUserInfo(): User;
}
