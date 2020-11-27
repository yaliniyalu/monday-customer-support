<?php

namespace App\Domain\Chat;

use JsonSerializable;

class User implements JsonSerializable
{
    /**
     * @var string
     */
    private string $id;
    /**
     * @var string|null
     */
    private ?string $name;

    public function __construct(string $id, ?string $name = null)
    {
        $this->id = $id;
        $this->name = $name;
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
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->name
        ];
    }

    public function toString()
    {
        if ($this->name) {
            return "{$this->name} <{$this->id}>";
        }

        return $this->id;
    }
}
