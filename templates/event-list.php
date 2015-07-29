<h2>
    <a href="<?php the_permalink() ?>" rel="bookmark"
       title="<?php the_title_attribute(); ?>">
        <?php the_title(); ?>
    </a>
</h2>
<?php
if (has_post_thumbnail()) :
    ?>
    <p>
        <a href="<?php the_permalink() ?>" rel="bookmark"
           title="<?php the_title_attribute(); ?>">
            <?php the_post_thumbnail() ?>
        </a>
    </p>
    <?php
endif;
?>
<p><?php the_content() ?></p>
<hr>
