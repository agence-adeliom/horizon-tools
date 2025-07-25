<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Admin;

use Adeliom\HorizonTools\Fields\Medias\ImageField;
use Adeliom\HorizonTools\Fields\Select\PostTypeSelectField;
use Adeliom\HorizonTools\Services\PostService;
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Message;
use Extended\ACF\Fields\Number;
use Extended\ACF\Fields\PostObject;
use Extended\ACF\Fields\Relationship;
use Extended\ACF\Fields\Tab;
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
    public const FIELD_ALLOW_FILTER_BY_TYPE = 'allowFilterByType';
    public const FIELD_PER_PAGE = 'perPage';
    public const FIELD_SEARCH_GET_PARAMETER = 'searchGetParameter';
    public const FIELD_META_TITLE = 'metaTitle';
    public const FIELD_SEARCH_HEADER_HAS_BREADCRUMBS = 'searchHeaderHasBreadcrumbs';
    public const FIELD_SEARCH_HEADER_TITLE = 'searchHeaderTitle';
    public const FIELD_SEARCH_HEADER_IMAGE = 'searchHeaderImage';
    public const FIELD_EXCLUDED_POSTS = 'searchExcludedPosts';

    public const SEARCH_PLACEHOLDER = '%SEARCH%';

    public function getFields(): ?iterable
    {
        yield Group::make(__('Moteur de recherche'), self::FIELD_HORIZON_SEARCH)->fields([
            Tab::make(__('Général'))->placement('left'),
            PostObject::make(__('Page des résultats'), self::FIELD_SEARCH_RESULTS_PAGE)
                ->nullable()
                ->helperText(__('Laisser vide pour désactiver le moteur de recherche')),
            PostTypeSelectField::make(label: __('Types de contenus'), name: self::FIELD_SEARCH_TYPES, with: ['page', 'post'])
                ->multiple()
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('Permet de sélectionner les types de contenus inclus dans le moteur de recherche')),
            Relationship::make(__('Éléments exclus'), self::FIELD_EXCLUDED_POSTS)
                ->postTypes(PostService::getAllPostTypeSlugs())
                ->postStatus(['publish'])
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('La page de résultats est automatiquement exclue.'))
                ->format('id'),
            Tab::make(__('Header'))->placement('left'),
            TrueFalse::make(__('Afficher le fil d’ariane'), self::FIELD_SEARCH_HEADER_HAS_BREADCRUMBS)
                ->stylized()
                ->conditionalLogic([$this->getCondition()]),
            Text::make(__('Titre affiché dans le header'), self::FIELD_SEARCH_HEADER_TITLE)
                ->required()
                ->helperText($this->getHelperText())
                ->conditionalLogic([$this->getCondition()])
                ->default(sprintf('%s "%s"', __('Recherche pour'), self::SEARCH_PLACEHOLDER)),
            ImageField::make(__('Image d’arrière-plan'), self::FIELD_SEARCH_HEADER_IMAGE)
                ->helperText(__('Laisser vide pour la désactiver'))
                ->previewSize('thumbnail'),
            Tab::make(__('Apparence'))->placement('left'),
            Message::make(__('Apparence - Aucune page définie'))
                ->body(__('Veuillez sélectionner une page de résultats pour configurer l’apparence du moteur de recherche.'))
                ->conditionalLogic([$this->getNotCondition()]),
            TrueFalse::make(__('Séparer par types'), self::FIELD_SEPARATE_BY_TYPES)
                ->stylized()
                ->default(false)
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('Si activé, les résultats seront séparés par type de contenu.'))
                ->wrapper(['width' => 50]),
            TrueFalse::make(__('Permettre de filtrer par type'), self::FIELD_ALLOW_FILTER_BY_TYPE)
                ->stylized()
                ->default(true)
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('Active ou non la possibilité de filtrer les résultats par type de contenu'))
                ->wrapper(['width' => 50]),
            Number::make(__('Éléments par page'), self::FIELD_PER_PAGE)
                ->min(-1)
                ->step(1)
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('Nombre d’éléments à afficher par page. Mettre -1 pour désactiver la pagination.')),
            Text::make(__('Paramètre GET pour la recherche'), self::FIELD_SEARCH_GET_PARAMETER)
                ->conditionalLogic([$this->getCondition()])
                ->helperText(__('Permet de définir le nom du paramètre GET utilisé pour la recherche.'))
                ->default('recherche'),
            Tab::make(__('SEO'))->placement('left'),
            Message::make(__('SEO - Aucune page définie'))
                ->body(__('Veuillez sélectionner une page de résultats pour configurer le SEO du moteur de recherche.'))
                ->conditionalLogic([$this->getNotCondition()]),
            Text::make(__('Titre de la page de résultats'), self::FIELD_META_TITLE)
                ->helperText($this->getHelperText())
                ->conditionalLogic([$this->getCondition()])
                ->default(sprintf('%s "%s"', __('Recherche pour'), self::SEARCH_PLACEHOLDER)),
        ]);
    }

    private function getHelperText(): string
    {
        return sprintf('%s %s', __('Pour afficher la recherche courante, utilisez :'), self::SEARCH_PLACEHOLDER);
    }

    private function getCondition(): ConditionalLogic
    {
        return ConditionalLogic::where(self::FIELD_SEARCH_RESULTS_PAGE, '!=empty');
    }

    private function getNotCondition(): ConditionalLogic
    {
        return ConditionalLogic::where(self::FIELD_SEARCH_RESULTS_PAGE, '==empty');
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
