# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses
[Semantic Versioning](https://semver.org/).

`composer.json` has no `version` field: Packagist takes the version from the git
tag, so this release is published by tagging `v1.0.0`.

## [1.0.0]

Validated against API contract 2.0.0.

### BREAKING

- `activeLiveness()` no longer returns a token: `ActiveLivenessResult::$livenessToken`
  and `$livenessTokenExpiresAt` are removed, because the API's stateless check no longer
  issues one. Liveness tokens now come only from completing a liveness session.

  Migration: create a session, show its challenges, complete it with the frames.

  ```php
  $session = LiveXFace::faces()->createLivenessSession($collectionId);
  // prompt $session->challenges in order, capture frames before $session->expiresAt
  $result = LiveXFace::faces()->completeLivenessSession($collectionId, $session->sessionId, $frames, mirrored: false);
  LiveXFace::faces()->register($collectionId, $frames[0], 'u1', livenessToken: $result->livenessToken);
  ```

### Added

- `FacesResource::createLivenessSession()` returning `LivenessSession` (`sessionId`,
  ordered `challenges`, `expiresAt`).
- `FacesResource::completeLivenessSession()` returning `LivenessSessionResult` (the
  active-liveness fields, `steps` of `LivenessStep`, and `livenessToken` /
  `livenessTokenExpiresAt` when it passed). Retried only on 429, never on 503 or a
  network error, since the session may already be used up.
- `LiveXFaceApiException::isLivenessSessionInvalid()` for 422 `LIVENESS_SESSION_INVALID`.

### Changed

- `LiveXFaceClient::CONTRACT_VERSION` is `2.0.0`; the pinned contract is
  `contract/openapi-2.0.0.json`.
