<h2><?php echo $data['status']; ?></h2>
<form name="loginform" id="loginform" action="<?php echo site_url(); ?>/wp-login.php" method="post">
    <p>
        <label for="user_login"><?php _e('Username', EVENTAPPI_PLUGIN_NAME); ?><br>
            <input type="text" name="log" id="user_login" class="input" value="" size="20">
        </label>
    </p>
    <p>
        <label for="user_pass"><?php _e('Password', EVENTAPPI_PLUGIN_NAME); ?><br>
            <input type="password" name="pwd" id="user_pass" class="input" value="" size="20">
        </label>
    </p>
    <p class="forgetmenot">
        <label for="rememberme">
            <input name="rememberme" type="checkbox" id="rememberme" value="forever"><?php _e('Remember Me', EVENTAPPI_PLUGIN_NAME); ?>
        </label>
    </p>
    <p class="submit">
        <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php _e('Log In', EVENTAPPI_PLUGIN_NAME); ?>" />
        <input type="hidden" name="redirect_to" value="" />
        <input type="hidden" name="testcookie" value="1" />
    </p>
</form>