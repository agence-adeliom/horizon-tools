<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Admin;

use Adeliom\HorizonTools\Fields\Select\PostTypeSelectField;
use Adeliom\HorizonTools\Services\SearchEngineService;
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Number;
use Extended\ACF\Fields\PostObject;
use Extended\ACF\Fields\Text;
use Extended\ACF\Fields\TrueFalse;

class SearchEngineOptionsAdmin extends AbstractAdmin
{
    public static ?string $title = 'Recherche';
    public static ?string $slug = 'search-engine-options';
    public static bool $isOptionPage = true;
    public static ?string $optionPageIcon = 'dashicons-search';

    public const FIELD_HORIZON_SEARCH = 'horizonSearch';
    public const FIELD_SEARCH_RESULTS_PAGE = 'searchResultsPage';
    public const FIELD_SEARCH_TYPES = 'searchTypes';
    public const FIELD_SEPARATE_BY_TYPES = 'separateByTypes';
    public const FIELD_PER_PAGE = 'perPage';
    public const FIELD_SEARCH_GET_PARAMETER = 'searchGetParameter';

    public function getFields(): ?iterable
    {
        yield Group::make(__('Moteur de recherche'), self::FIELD_HORIZON_SEARCH)->fields([
            PostObject::make(__('Page des résultats'), self::FIELD_SEARCH_RESULTS_PAGE)
                ->nullable()
                ->helperText(__('Laisser vide pour désactiver le moteur de recherche')),
            PostTypeSelectField::make(label: __('Types de contenus'), name: self::FIELD_SEARCH_TYPES, with: ['page', 'post'])
                ->multiple()
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('Permet de sélectionner les types de contenus inclus dans le moteur de recherche')),
            TrueFalse::make(__('Séparer par types'), self::FIELD_SEPARATE_BY_TYPES)
                ->stylized()
                ->default(false)
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('Si activé, les résultats seront séparés par type de contenu.')),
            Number::make(__('Éléments par page'), self::FIELD_PER_PAGE)
                ->min(-1)
                ->step(1)
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('Nombre d’éléments à afficher par page. Mettre -1 pour désactiver la pagination.')),
            Text::make(__('Paramètre GET pour la recherche'), self::FIELD_SEARCH_GET_PARAMETER)
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('Permet de définir le nom du paramètre GET utilisé pour la recherche.'))
                ->default('recherche'),
        ]);
    }

    private function getCondition(): ConditionalLogic
    {
        return ConditionalLogic::where(self::FIELD_SEARCH_RESULTS_PAGE, '!=empty');
    }

    public function getStyle(): string
    {
        return 'default';
    }

    public function getOptionPageParent(): ?string
    {
        return null;
    }

    public function getLocation(): iterable
    {
        yield from parent::getLocation();
    }
}
