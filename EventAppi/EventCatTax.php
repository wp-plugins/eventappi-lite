<?php
namespace EventAppi;

use EventAppi\Helpers\Logger;

/**
 *
 */
define('EVENTAPPI_CAT_TAX_NAME', EVENTAPPI_POST_NAME.'_categories');
/**
 * Class EventCatTax
 *
 * @package EventAppi
 * Handles the Category Taxonomy (For the Events)
 */
class EventCatTax
{
    /**
     * @var EventCatTax|null
     */
    private static $singleton = null;

    /**
     *
     */
    const TAX_NAME = EVENTAPPI_CAT_TAX_NAME;
    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return EventCatTax|null
     */
    public static function instance()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    /**
     *
     */
    public function init()
    {

    }

    /**
     *
     */
    public function createCategoryTaxonomy()
    {
        $labels = array(
            'name'                       => _x('Categories', 'taxonomy general name', EVENTAPPI_PLUGIN_NAME),
            'singular_name'              => _x('Category', 'taxonomy singular name', EVENTAPPI_PLUGIN_NAME),
            'search_items'               => __('Search Categories', EVENTAPPI_PLUGIN_NAME),
            'popular_items'              => __('Popular Categories', EVENTAPPI_PLUGIN_NAME),
            'all_items'                  => __('All Categories', EVENTAPPI_PLUGIN_NAME),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Category', EVENTAPPI_PLUGIN_NAME),
            'update_item'                => __('Update Category', EVENTAPPI_PLUGIN_NAME),
            'add_new_item'               => __('Add New Category', EVENTAPPI_PLUGIN_NAME),
            'new_item_name'              => __('New Category Name', EVENTAPPI_PLUGIN_NAME),
            'separate_items_with_commas' => __('Separate categories with commas', EVENTAPPI_PLUGIN_NAME),
            'add_or_remove_items'        => __('Add or remove categories', EVENTAPPI_PLUGIN_NAME),
            'choose_from_most_used'      => __('Choose from the most used categories', EVENTAPPI_PLUGIN_NAME),
            'not_found'                  => __('No category found.', EVENTAPPI_PLUGIN_NAME),
            'menu_name'                  => __('Categories', EVENTAPPI_PLUGIN_NAME)
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => false,
            'public'            => false
        );

        register_taxonomy(self::TAX_NAME, EVENTAPPI_POST_NAME, $args);
    }

    /**
     * @param $eventId
     *
     * @return array
     */
    public function getList($eventId)
    {
        $theCats = [];
        $terms = get_the_terms($eventId, EventCatTax::TAX_NAME);
        if (! empty($terms)) {
            $taxonomy = get_taxonomies(['name' => EventCatTax::TAX_NAME], 'objects');

            $theCats['label'] = $taxonomy[EventCatTax::TAX_NAME]->label;

            foreach ($terms as $term) {
                $theCats['names'][] = trim($term->name);
            }
        }
        return $theCats;
    }

}
