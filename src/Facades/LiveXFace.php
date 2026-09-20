<?php

namespace LiveXFace\Facades;

use LiveXFace\Resources\CollectionsResource;
use LiveXFace\Resources\FacesResource;
use Illuminate\Support\Facades\Facade;

/**
 * @method static FacesResource faces()
 * @method static CollectionsResource collections()
 *
 * @see \LiveXFace\LiveXFaceClient
 */
class LiveXFace extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'livexface';
    }
}
