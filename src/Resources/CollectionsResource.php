<?php

namespace FrApiaas\Resources;

use FrApiaas\DTO\FaceCollection;
use FrApiaas\FrClient;

/** Face collection management. */
class CollectionsResource
{
    public function __construct(private readonly FrClient $client)
    {
    }

    /** @return FaceCollection[] */
    public function list(): array
    {
        $data = $this->client->call(fn ($req) => $req->get('/collections'));

        // The endpoint returns a bare array in `data`.
        $items = array_is_list($data) ? $data : ($data['collections'] ?? []);

        return array_map(FaceCollection::fromArray(...), $items);
    }

    public function get(string $collectionId): FaceCollection
    {
        $data = $this->client->call(fn ($req) => $req->get("/collections/{$collectionId}"));

        return FaceCollection::fromArray($data);
    }

    public function create(string $name, string $description = '', ?int $retentionDays = null): FaceCollection
    {
        $body = ['name' => $name, 'description' => $description];
        if ($retentionDays !== null) {
            $body['retention_days'] = $retentionDays;
        }

        $data = $this->client->call(fn ($req) => $req->post('/collections', $body));

        return FaceCollection::fromArray($data);
    }

    public function update(
        string $collectionId,
        ?string $name = null,
        ?string $description = null,
        ?int $retentionDays = null,
    ): FaceCollection {
        $body = array_filter([
            'name' => $name,
            'description' => $description,
            'retention_days' => $retentionDays,
        ], fn ($v) => $v !== null);

        $data = $this->client->call(fn ($req) => $req->put("/collections/{$collectionId}", $body));

        return FaceCollection::fromArray($data);
    }

    public function delete(string $collectionId): void
    {
        $this->client->call(fn ($req) => $req->delete("/collections/{$collectionId}"));
    }
}
