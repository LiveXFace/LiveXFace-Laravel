# FR-APIaaS Laravel SDK

Official Laravel SDK for [FR-APIaaS](https://fr-apiaas.io) — Face Recognition as a Service.

Built on Laravel's HTTP client, so `Http::fake()` works out of the box in your tests.

## Installation

```bash
composer require fr-apiaas/laravel-sdk
```

Add your credentials to `.env`:

```dotenv
FR_APIAAS_KEY=fras_xxxxxxxxxxxx
# Optional — defaults to the hosted cloud; point at your on-prem instance if self-hosting:
# FR_APIAAS_URL=https://faces.internal.example.com/api/v1
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=fr-apiaas-config
```

## Usage

```php
use FrApiaas\Facades\FrApiaas;

// Enroll a face
$face = FrApiaas::faces()->register(
    $collectionId,
    file_get_contents($request->file('photo')->path()),
    externalId: 'user_'.$user->id,
    metadata: ['name' => $user->name],
);

// 1:N identify
$result = FrApiaas::faces()->identify($collectionId, $imageBytes, topK: 3);
if ($best = $result->best()) {
    // $best->externalId, $best->confidence
}

// 1:1 verify against a stored face
$verdict = FrApiaas::faces()->verify($collectionId, $imageBytes, faceId: $face->id);

// Liveness (anti-spoofing)
$live = FrApiaas::faces()->liveness($collectionId, $imageBytes);

// Face attributes (age, gender, emotion, glasses, mask, head pose)
$attrs = FrApiaas::faces()->attributes($collectionId, $imageBytes);

// Async batch registration (up to 100 images)
$job = FrApiaas::faces()->batchRegisterAsync($collectionId, [
    ['external_id' => 'u1', 'image' => $bytes1],
    ['external_id' => 'u2', 'image' => $bytes2, 'metadata' => ['team' => 'sales']],
]);
while (! $job->isFinished()) {
    sleep(1);
    $job = FrApiaas::faces()->getBatchJob($collectionId, $job->id);
}
```

Dependency injection works too — type-hint `FrApiaas\FrClient` anywhere.

## Error handling

All API errors raise `FrApiaas\Exceptions\FrApiException` carrying the API
error code, HTTP status, and request id:

```php
try {
    FrApiaas::faces()->identify($collectionId, $bytes);
} catch (\FrApiaas\Exceptions\FrApiException $e) {
    if ($e->isNoFaceDetected()) { /* ask the user for a clearer photo */ }
}
```

Transport failures (DNS, timeouts) raise `FrApiaas\Exceptions\FrNetworkException`.

## Requirements

- PHP >= 8.1
- Laravel 10, 11, or 12
