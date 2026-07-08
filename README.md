# Serupa Laravel SDK

Official Laravel SDK for [Serupa](https://serupa.ai) — Face Recognition as a Service.

Built on Laravel's HTTP client, so `Http::fake()` works out of the box in your tests.

## Installation

```bash
composer require serupa/laravel-sdk
```

Add your credentials to `.env`:

```dotenv
SERUPA_KEY=srp_xxxxxxxxxxxx
# Optional — defaults to the hosted cloud; point at your on-prem instance if self-hosting:
# SERUPA_URL=https://faces.internal.example.com/api/v1
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=serupa-config
```

## Usage

```php
use Serupa\Facades\Serupa;

// Enroll a face
$face = Serupa::faces()->register(
    $collectionId,
    file_get_contents($request->file('photo')->path()),
    externalId: 'user_'.$user->id,
    metadata: ['name' => $user->name],
);

// 1:N identify
$result = Serupa::faces()->identify($collectionId, $imageBytes, topK: 3);
if ($best = $result->best()) {
    // $best->externalId, $best->confidence
}

// 1:1 verify against a stored face
$verdict = Serupa::faces()->verify($collectionId, $imageBytes, faceId: $face->id);

// Liveness (anti-spoofing)
$live = Serupa::faces()->liveness($collectionId, $imageBytes);

// Face attributes (age, gender, emotion, glasses, mask, head pose)
$attrs = Serupa::faces()->attributes($collectionId, $imageBytes);

// Async batch registration (up to 100 images)
$job = Serupa::faces()->batchRegisterAsync($collectionId, [
    ['external_id' => 'u1', 'image' => $bytes1],
    ['external_id' => 'u2', 'image' => $bytes2, 'metadata' => ['team' => 'sales']],
]);
while (! $job->isFinished()) {
    sleep(1);
    $job = Serupa::faces()->getBatchJob($collectionId, $job->id);
}
```

Dependency injection works too — type-hint `Serupa\SerupaClient` anywhere.

## Error handling

All API errors raise `Serupa\Exceptions\SerupaApiException` carrying the API
error code, HTTP status, and request id:

```php
try {
    Serupa::faces()->identify($collectionId, $bytes);
} catch (\Serupa\Exceptions\SerupaApiException $e) {
    if ($e->isNoFaceDetected()) { /* ask the user for a clearer photo */ }
}
```

Transport failures (DNS, timeouts) raise `Serupa\Exceptions\SerupaNetworkException`.

## Requirements

- PHP >= 8.1
- Laravel 10, 11, or 12
