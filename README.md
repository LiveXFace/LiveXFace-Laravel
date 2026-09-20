<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/brand/logo-white.svg">
    <img src="docs/brand/logo.svg" alt="LiveXFace" width="220">
  </picture>
</p>

# LiveXFace Laravel SDK

Official Laravel SDK for [LiveXFace](https://livexface.com) — Face Recognition as a Service.

Built on Laravel's HTTP client, so `Http::fake()` works out of the box in your tests.

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
error code, HTTP status, and request id:

```php
try {
    LiveXFace::faces()->identify($collectionId, $bytes);
} catch (\LiveXFace\Exceptions\LiveXFaceApiException $e) {
    if ($e->isNoFaceDetected()) { /* ask the user for a clearer photo */ }
}
```

Transport failures (DNS, timeouts) raise `LiveXFace\Exceptions\LiveXFaceNetworkException`.

## Requirements

- PHP >= 8.1
- Laravel 10, 11, or 12
