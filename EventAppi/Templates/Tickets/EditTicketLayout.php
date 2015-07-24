<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="cmb_metabox">
    <?php
    /*
     * The code is based on the one from CMB_Meta_Box::layout_fields( $fields )
     */
    $current_colspan = 0;
    
    $fields = $data['fields'];

    foreach ( $fields as $field ) :

            if ( $current_colspan == 0 ) : ?>
                <div class="cmb-row">

            <?php endif;

            $current_colspan += $field->args['cols'];

            $classes = array( 'field', get_class($field) );

            if ( ! empty( $field->args['repeatable'] ) )
                    $classes[] = 'repeatable';

            if ( ! empty( $field->args['sortable'] ) )
                    $classes[] = 'cmb-sortable';

            $attrs = array(
                    sprintf( 'class="%s"', esc_attr( implode(' ', array_map( 'sanitize_html_class', $classes ) ) ) )
            );

            // Field Repeatable Max.
            if ( isset( $field->args['repeatable_max']  ) )
                    $attrs[] = sprintf( 'data-rep-max="%s"', intval( $field->args['repeatable_max'] ) );

            ?>

            <div class="cmb-cell-<?php echo intval( $field->args['cols'] ); ?>">
                <div <?php echo implode( ' ', $attrs ); ?>>
                        <?php $field->display(); ?>
                </div>
                <input type="hidden" name="_cmb_present_<?php esc_attr_e( $field->id ); ?>" value="1" />
            </div>
            <?php if ( $current_colspan == 12 || $field === end( $fields ) ) :
                    $current_colspan = 0; ?>
                    </div><!-- .cmb-row -->
            <?php endif; ?>
    <?php endforeach; ?>
</div>  