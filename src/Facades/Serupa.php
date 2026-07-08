<?php

namespace Serupa\Facades;

use Serupa\Resources\CollectionsResource;
use Serupa\Resources\FacesResource;
use Illuminate\Support\Facades\Facade;

/**
 * @method static FacesResource faces()
 * @method static CollectionsResource collections()
 *
 * @see \Serupa\SerupaClient
 */
class Serupa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'serupa';
    }
}
