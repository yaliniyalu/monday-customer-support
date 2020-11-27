<?php

namespace App\Service;

use App\Domain\JwtConfig;
use Exception;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;

class AuthenticationService
{
    private JwtConfig $config;

    public function __construct(JwtConfig $config)
    {
        $this->config = $config;
    }

    public function generateToken(array $claims = []): Token
    {
        $signer = new Sha256();
        $time = time();

        $token = (new Builder())->issuedBy($this->config->issuedBy)
            ->permittedFor($this->config->permittedFor)
            ->identifiedBy($this->config->identifiedBy, true)
            ->issuedAt($time)
            ->expiresAt($time + $this->config->expireAfter);

        foreach ($claims as $key => $claim) {
            $token->withClaim($key, $claim);
        }

        return $token->getToken($signer, new Key($this->config->signingSecret));
    }

    /**
     * @param string $token
     * @return \Lcobucci\JWT\Token
     * @throws Exception
     */
    public function verifyToken(string $token): Token
    {
        $token = (new Parser())->parse((string) $token);
        if (!$token->verify(new Sha256(), $this->config->signingSecret)) {
            throw new Exception("Token verification failed");
        }
        return $token;
    }
}
