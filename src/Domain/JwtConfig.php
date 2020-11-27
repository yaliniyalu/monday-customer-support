<?php

namespace App\Domain;

class JwtConfig
{
    public string $issuedBy;
    public string $permittedFor;
    public string $identifiedBy;
    public string $signingSecret;
    public int $expireAfter;
}
