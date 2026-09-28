<?php

namespace LiveXFace\DTO;

/**
 * Result of an active (multi-frame) liveness check. When the check passes,
 * $livenessToken is set: a single-use token, valid for 5 minutes and bound to
 * the organization and collection, that register() / batch entries accept.
 */
final class ActiveLivenessResult
{
    public function __construct(
        public readonly bool $isLive,
        public readonly float $overallScore,
        public readonly int $framesAnalyzed,
        public readonly int $framesWithFace,
        public readonly LivenessChallenge $blink,
        public readonly LivenessChallenge $headTurn,
        public readonly LivenessChallenge $passiveAntispoof,
        public readonly ?string $livenessToken,
        public readonly ?string $livenessTokenExpiresAt,
    ) {
    }

    public static function fromArray(array $d): self
    {
        $c = $d['challenges'] ?? [];

        return new self(
            isLive: $d['isLive'] ?? false,
            overallScore: (float) ($d['overallScore'] ?? 0),
            framesAnalyzed: (int) ($d['framesAnalyzed'] ?? 0),
            framesWithFace: (int) ($d['framesWithFace'] ?? 0),
            blink: LivenessChallenge::fromArray($c['blink'] ?? []),
            headTurn: LivenessChallenge::fromArray($c['headTurn'] ?? []),
            passiveAntispoof: LivenessChallenge::fromArray($c['passiveAntispoof'] ?? []),
            livenessToken: $d['livenessToken'] ?? null,
            livenessTokenExpiresAt: $d['livenessTokenExpiresAt'] ?? null,
        );
    }
}
