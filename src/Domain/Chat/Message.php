<?php

namespace App\Domain\Chat;

use JsonSerializable;

class Message implements JsonSerializable
{
    /**
     * @var string
     */
    private string $id;
    /**
     * @var string
     */
    private string $chatId;
    /**
     * @var User|null
     */
    private ?User $from;
    /**
     * @var User|null
     */
    private ?User $to;
    /**
     * @var string
     */
    private ?string $text;
    /**
     * @var string
     */
    private ?string $html;
    /**
     * @var Attachment[]
     */
    private array $attachments;

    private string $date;
    /**
     * @var boolean
     */
    private bool $isRead;
    /**
     * @var integer
     * */
    private int $index;

    private string $subject;

    private array $attributes = [];

    public function __construct(string $id, string $chatId, ?User $from, ?User $to)
    {
        $this->id = $id;
        $this->from = $from;
        $this->to = $to;
        $this->chatId = $chatId;

        $this->attachments = [];
        $this->text = null;
        $this->html = null;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'text' => $this->text,
            'html' => $this->html,
            'attachments' => $this->attachments,
            'date' => $this->date,
            'isRead' => $this->isRead,
            'index' => $this->index,
            'chatId' => $this->chatId
        ];
    }

    /**
     * @return string
     */
    public function getChatId(): string
    {
        return $this->chatId;
    }

    /**
     * @param string $chatId
     */
    public function setChatId(string $chatId): void
    {
        $this->chatId = $chatId;
    }

    /**
     * @return User
     */
    public function getFrom(): User
    {
        return $this->from;
    }

    /**
     * @param User $from
     */
    public function setFrom(User $from): void
    {
        $this->from = $from;
    }

    /**
     * @return User
     */
    public function getTo(): User
    {
        return $this->to;
    }

    /**
     * @param User $to
     */
    public function setTo(User $to): void
    {
        $this->to = $to;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @param string $text
     */
    public function setText(string $text): void
    {
        $this->text = $text;
    }

    /**
     * @return string|null
     */
    public function getHtml(): ?string
    {
        return $this->html;
    }

    /**
     * @param string|null $html
     */
    public function setHtml(?string $html): void
    {
        $this->html = $html;
    }

    /**
     * @return Attachment[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param Attachment[] $attachments
     */
    public function setAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    /**
     * @param Attachment $attachment
     */
    public function addAttachment(Attachment $attachment): void
    {
        $this->attachments[] = $attachment;
    }

    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * @param string $date
     */
    public function setDate(string $date): void
    {
        $this->date = $date;
    }

    /**
     * @return bool
     */
    public function isRead(): bool
    {
        return $this->isRead;
    }

    /**
     * @param bool $isRead
     */
    public function setIsRead(bool $isRead): void
    {
        $this->isRead = $isRead;
    }

    /**
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @param int $index
     */
    public function setIndex(int $index): void
    {
        $this->index = $index;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     */
    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return string
     */
    public function getAttribute(string $key, $default = null): string
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function setAttribute(string $key, string $value): void
    {
        $this->attributes[$key] = $value;
    }
}
