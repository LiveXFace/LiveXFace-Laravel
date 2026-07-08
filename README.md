# Idemity Laravel SDK

Official Laravel SDK for [Idemity](https://idemity.com) — Face Recognition as a Service.

Built on Laravel's HTTP client, so `Http::fake()` works out of the box in your tests.

## Installation

```bash
composer require idemity/laravel-sdk
```

Add your credentials to `.env`:

```dotenv
IDEMITY_KEY=idm_xxxxxxxxxxxx
# Optional — defaults to the hosted cloud; point at your on-prem instance if self-hosting:
# IDEMITY_URL=https://faces.internal.example.com/api/v1
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=idemity-config
```

## Usage

```php
use Idemity\Facades\Idemity;

// Enroll a face
$face = Idemity::faces()->register(
    $collectionId,
    file_get_contents($request->file('photo')->path()),
    externalId: 'user_'.$user->id,
    metadata: ['name' => $user->name],
);

// 1:N identify
$result = Idemity::faces()->identify($collectionId, $imageBytes, topK: 3);
if ($best = $result->best()) {
    // $best->externalId, $best->confidence
}

// 1:1 verify against a stored face
$verdict = Idemity::faces()->verify($collectionId, $imageBytes, faceId: $face->id);

// Liveness (anti-spoofing)
$live = Idemity::faces()->liveness($collectionId, $imageBytes);

// Face attributes (age, gender, emotion, glasses, mask, head pose)
$attrs = Idemity::faces()->attributes($collectionId, $imageBytes);

// Async batch registration (up to 100 images)
$job = Idemity::faces()->batchRegisterAsync($collectionId, [
    ['external_id' => 'u1', 'image' => $bytes1],
    ['external_id' => 'u2', 'image' => $bytes2, 'metadata' => ['team' => 'sales']],
]);
while (! $job->isFinished()) {
    sleep(1);
    $job = Idemity::faces()->getBatchJob($collectionId, $job->id);
}
```

Dependency injection works too — type-hint `Idemity\IdemityClient` anywhere.

## Error handling

All API errors raise `Idemity\Exceptions\IdemityApiException` carrying the API
error code, HTTP status, and request id:

```php
try {
    Idemity::faces()->identify($collectionId, $bytes);
} catch (\Idemity\Exceptions\IdemityApiException $e) {
    if ($e->isNoFaceDetected()) { /* ask the user for a clearer photo */ }
}
```

Transport failures (DNS, timeouts) raise `Idemity\Exceptions\IdemityNetworkException`.

## Requirements

- PHP >= 8.1
- Laravel 10, 11, or 12
