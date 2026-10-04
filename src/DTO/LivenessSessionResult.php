<?php

namespace LiveXFace\DTO;

/**
 * Result of completing a liveness session: the active-liveness verdict plus
 * $steps, the session's challenges in order. When it passes, $livenessToken
 * is set: a single-use token, valid for 5 minutes and bound to the
 * organization and collection, that register() / batch entries accept.
 */
final class LivenessSessionResult
{
    /** @param LivenessStep[] $steps */
    public function __construct(
        public readonly bool $isLive,
        public readonly float $overallScore,
        public readonly int $framesAnalyzed,
        public readonly int $framesWithFace,
        public readonly LivenessChallenge $blink,
        public readonly LivenessChallenge $headTurn,
        public readonly LivenessChallenge $passiveAntispoof,
        public readonly array $steps,
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
            steps: array_map(LivenessStep::fromArray(...), $d['steps'] ?? []),
            livenessToken: $d['livenessToken'] ?? null,
            livenessTokenExpiresAt: $d['livenessTokenExpiresAt'] ?? null,
        );
    }
}
