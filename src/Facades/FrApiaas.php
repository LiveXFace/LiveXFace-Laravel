<?php

namespace FrApiaas\Facades;

use FrApiaas\Resources\CollectionsResource;
use FrApiaas\Resources\FacesResource;
use Illuminate\Support\Facades\Facade;

/**
 * @method static FacesResource faces()
 * @method static CollectionsResource collections()
 *
 * @see \FrApiaas\FrClient
 */
class FrApiaas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'fr-apiaas';
    }
}
