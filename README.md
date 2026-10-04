<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/brand/logo-white.svg">
    <img src="docs/brand/logo.svg" alt="LiveXFace" width="220">
  </picture>
</p>

# LiveXFace Laravel SDK

Official Laravel SDK for [LiveXFace](https://livexface.com) — Face Recognition as a Service.

Built on Laravel's HTTP client, so `Http::fake()` works out of the box in your tests.

Validated against API contract 2.0.0 (`/openapi.json` `info.version`), also exposed as `LiveXFaceClient::CONTRACT_VERSION`. `tests/ContractTest.php` calls every SDK method and checks its HTTP method, path and required fields against the pinned `contract/openapi-2.0.0.json`; to move to a new contract, copy the release asset `openapi-<version>.json` into `contract/` and update `CONTRACT_VERSION` and the constant.

## Installation

```bash
composer require livexface/laravel-sdk
```

Add your credentials to `.env`:

```dotenv
LIVEXFACE_KEY=lxf_xxxxxxxxxxxx
# Optional — defaults to the hosted cloud; point at your on-prem instance if self-hosting:
# LIVEXFACE_URL=https://faces.internal.example.com/api/v1
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=livexface-config
```

## Usage

```php
use LiveXFace\Facades\LiveXFace;

// Enroll a face
$face = LiveXFace::faces()->register(
    $collectionId,
    file_get_contents($request->file('photo')->path()),
    externalId: 'user_'.$user->id,
    metadata: ['name' => $user->name],
);

// 1:N identify
$result = LiveXFace::faces()->identify($collectionId, $imageBytes, topK: 3);
if ($best = $result->best()) {
    // $best->externalId, $best->confidence
}

// 1:1 verify against a stored face
$verdict = LiveXFace::faces()->verify($collectionId, $imageBytes, faceId: $face->id);

// Liveness (anti-spoofing)
$live = LiveXFace::faces()->liveness($collectionId, $imageBytes);

// Liveness session: the server picks the steps, you show them, then submit the frames
$session = LiveXFace::faces()->createLivenessSession($collectionId);
// $session->challenges, in order: 'blink', 'turn_left' or 'turn_right' (the person's own left/right).
// Show each prompt and capture 5-50 frames (JPEG/PNG bytes) before $session->expiresAt (60 s).
$result = LiveXFace::faces()->completeLivenessSession(
    $collectionId,
    $session->sessionId,
    $frames,
    mirrored: false, // true if the frames are flipped like a selfie preview
);
// $result->steps: each step's ->type and ->passed
if ($result->isLive && $result->livenessToken !== null) {
    $face = LiveXFace::faces()->register(
        $collectionId,
        $frames[0],
        externalId: 'user_'.$user->id,
        livenessToken: $result->livenessToken, // valid 5 minutes, one use
    );
}
// Batch entries take it too: ['external_id' => 'u1', 'image' => $b, 'liveness_token' => $t]

// Stateless active liveness: a verdict only, no token
$check = LiveXFace::faces()->activeLiveness($collectionId, $frames);

// Face attributes (age, gender, emotion, glasses, mask, head pose)
$attrs = LiveXFace::faces()->attributes($collectionId, $imageBytes);

// Async batch registration (up to 100 images)
$job = LiveXFace::faces()->batchRegisterAsync($collectionId, [
    ['external_id' => 'u1', 'image' => $bytes1],
    ['external_id' => 'u2', 'image' => $bytes2, 'metadata' => ['team' => 'sales']],
]);
while (! $job->isFinished()) {
    sleep(1);
    $job = LiveXFace::faces()->getBatchJob($collectionId, $job->id);
}
```

Dependency injection works too — type-hint `LiveXFace\LiveXFaceClient` anywhere.

## Error handling

All API errors raise `LiveXFace\Exceptions\LiveXFaceApiException` carrying the API
error code (`errorCode`), HTTP `status`, `requestId`, `details` (when the API sends
them) and `retryAfter` (seconds from a `Retry-After` header, else `null`; also
`getRetryAfter()`):

```php
try {
    LiveXFace::faces()->identify($collectionId, $bytes);
} catch (\LiveXFace\Exceptions\LiveXFaceApiException $e) {
    if ($e->isNoFaceDetected()) { /* ask the user for a clearer photo */ }
}
```

A liveness session is judged once. `completeLivenessSession()` answers 422
`LIVENESS_SESSION_INVALID` (`$e->isLivenessSessionInvalid()`) when the session is
unknown, expired, already submitted, or bound to another collection; 400
`IMAGE_REQUIRED` (fewer than 5 frames) keeps the session. On either error, a
503 `SERVICE_BUSY` or a network error, create a new session and capture again.

Transport failures (DNS, timeouts) raise `LiveXFace\Exceptions\LiveXFaceNetworkException`.

## Idempotent requests

`register()`, `batchRegister()` and `batchRegisterAsync()` take an `idempotencyKey`,
sent as the `Idempotency-Key` header. For 24 hours, repeating a call with the same
key returns the stored answer instead of enrolling again; a replayed answer carries
the `Idempotent-Replayed: true` header. Reusing a key for a different request is
answered 422 `IDEMPOTENCY_KEY_MISMATCH`, and while the first call is still running,
409 `IDEMPOTENCY_KEY_IN_USE`.

429 and 5xx answers are not remembered, so retrying with the same key runs the
request again. Any other 4xx (e.g. 422 `NO_FACE_DETECTED`) is remembered: a new
attempt, say with a better photo, needs a new key.

```php
use LiveXFace\LiveXFaceClient;

$key = LiveXFaceClient::generateIdempotencyKey(); // a random UUID v4; store it with your job
$face = LiveXFace::faces()->register($collectionId, $bytes, externalId: 'u1', idempotencyKey: $key);
```

## Production retries

Retries are off by default. Turn them on with `LIVEXFACE_MAX_RETRIES` (attempts after
the first; `LIVEXFACE_MAX_RETRY_DELAY` caps the wait, default 60 s), or
`maxRetries:` / `maxRetryDelay:` when constructing the client yourself:

- 429 and 503 are retried after their `Retry-After`, or an exponential backoff with jitter;
- network errors and other 5xx are retried only for reads, deletes and calls that carry an
  idempotency key, so a plain POST such as `identify()` is never sent twice after it may have run;
- other 4xx are never retried;
- `completeLivenessSession()` is retried only on 429, which the rate limiter answers before
  the session is touched. A 503 or network error may come after the session was used up, so
  it is raised for you to start a new session rather than retried into `LIVENESS_SESSION_INVALID`.

Enrolment and batch calls send one idempotency key on every attempt, generating it
when you gave none.

```php
$client = new LiveXFaceClient(apiKey: env('LIVEXFACE_KEY'), maxRetries: 3);

try {
    $face = $client->faces()->register(
        $collectionId,
        $bytes,
        externalId: 'u1',
        idempotencyKey: $job->idempotencyKey, // the same key if your job itself is retried
    );
} catch (\LiveXFace\Exceptions\LiveXFaceApiException $e) {
    if ($e->isRateLimited()) {
        $this->release($e->retryAfter ?? 30); // requeue the job
    }
    Log::warning('enrolment failed', ['code' => $e->errorCode, 'requestId' => $e->requestId]);
}
```

## Requirements

- PHP >= 8.1
- Laravel 10, 11, or 12
