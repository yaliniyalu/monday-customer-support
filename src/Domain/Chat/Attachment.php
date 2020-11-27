<?php

namespace App\Domain\Chat;

use GuzzleHttp\Psr7\Stream;
use JsonSerializable;

class Attachment implements JsonSerializable
{
    private string $mime;
    private string $name;
    private ?string $url;
    private string $id;
    private ?Stream $data = null;

    public function __construct(string $id, string $name, string $mime, ?string $url = null)
    {
        $this->id = $id;
        $this->mime = $mime;
        $this->name = $name;
        $this->url = $url;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mime' => $this->mime,
            'url' => $this->url
        ];
    }

    /**
     * @return string
     */
    public function getMime(): string
    {
        return $this->mime;
    }

    /**
     * @param string $mime
     */
    public function setMime(string $mime): void
    {
        $this->mime = $mime;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param string|null $url
     */
    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return Stream|null
     */
    public function getData(): ?Stream
    {
        return $this->data;
    }

    /**
     * @param Stream|null $data
     */
    public function setData(?Stream $data): void
    {
        $this->data = $data;
    }
}
