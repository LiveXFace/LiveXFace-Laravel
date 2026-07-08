<?php

namespace Idemity\Facades;

use Idemity\Resources\CollectionsResource;
use Idemity\Resources\FacesResource;
use Illuminate\Support\Facades\Facade;

/**
 * @method static FacesResource faces()
 * @method static CollectionsResource collections()
 *
 * @see \Idemity\IdemityClient
 */
class Idemity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'idemity';
    }
}
