<?php

namespace LiveXFace\Facades;

use LiveXFace\Resources\FacesResource;
use Illuminate\Support\Facades\Facade;

/**
 * @method static FacesResource faces()
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
