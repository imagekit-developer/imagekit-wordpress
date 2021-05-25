<?php
require_once (ABSPATH . 'wp-admin/includes/plugin-install.php');

function get_plugin_file($plugin_slug) {
  require_once (ABSPATH . '/wp-admin/includes/plugin.php');
  $plugins = get_plugins();

  foreach ($plugins as $plugin_file => $plugin_info) {
    $slug = dirname(plugin_basename($plugin_file));
    if ($slug) {
      if ($slug == $plugin_slug) {
        return $plugin_file;
      }
    }
  }
  return null;
}

function check_file_extension($filename) {
  if (substr(strrchr($filename, '.') , 1) === 'php') {
    return true;
  }
  else {
    return false;
  }
}

function imagekit_render_imagekit_setting_page() {
  global $imagekit_options;

  $plugin = "wp-lazy-loading";

  $api = plugins_api('plugin_information', array(
    'slug' => $plugin,
    'fields' => array(
      'short_description' => true,
      'sections' => false,
      'requires' => false,
      'downloaded' => true,
      'last_updated' => false,
      'added' => false,
      'tags' => false,
      'compatibility' => false,
      'homepage' => false,
      'donate_link' => false,
      'icons' => true,
      'banners' => true,
    ) ,
  ));

  $imagekit_options["cname"] = !empty($imagekit_options["cname"]) ? $imagekit_options["cname"] : "";
  $imagekit_options["file_type"] = !empty($imagekit_options["file_type"]) ? $imagekit_options["file_type"] : "*.gif;*.png;*.jpg;*.jpeg;*.bmp;*.ico;*.webp";
  $imagekit_options["custom_files"] = !empty($imagekit_options["custom_files"]) ? $imagekit_options["custom_files"] : "favicon.ico\ncustom-directory";
  $imagekit_options["reject_files"] = !empty($imagekit_options["reject_files"]) ? $imagekit_options["reject_files"] : "wp-content/uploads/wpcf7_captcha/*\nwp-content/uploads/imagerotator.swf\ncustom-directory*.mp4";

  if (empty($imagekit_options["imagekit_url_endpoint"])) {
    if (empty($imagekit_options['imagekit_id']) && empty($imagekit_options['cname'])) {
      $imagekit_options["imagekit_url_endpoint"] = "";
    }
    else if (!empty($imagekit_options['cname'])) {
      $imagekit_options["imagekit_url_endpoint"] = $imagekit_options['cname'];
    }
    else if (!empty($imagekit_options['imagekit_id'])) {
      $imagekit_options["imagekit_url_endpoint"] = "https://ik.imagekit.io/" . $imagekit_options['imagekit_id'];
    }
  }

  ob_start();

  wp_enqueue_style('xyz', plugins_url('imagekit') . '/includes/main.css');
?>
<div>
   <div id="ik-plugin-container">
      <div>
         <div>
            <div class="ik-masthead">
               <div class="ik-masthead__inside-container">
                  <div class="ik-masthead__logo-container">
                     <a class="ik-masthead__logo-link" href="#">
                        <img src="https://imagekit.io/static/img/newPages/image-kit-footer-logo.svg" class="imagekit-logo__masthead" height="32">
                     </a>
                  </div>
               </div>
            </div>
            <div class="ik-lower">
                <div class="ik-settings-container">
                    <div>
                        <div class="dops-card ik-settings-description">
                           <h2 class="dops-card-title">Steps to configure ImageKit.io</h2>
                           <h4>If you haven't created an account with ImageKit.io yet, then the first step is to 
            <a href="https://imagekit.io/registration" target="_blank">register</a>.
            
            After sign-up, check out <a href="https://docs.imagekit.io/platform-guides/wordpress" target="_blank">WordPress integration guide</a>.</h4>
                        </div>
                    </div>
                    <form method="post" action="options.php">
                        <?php settings_fields('imagekit_settings_group'); ?>
                        <div class="ik-form-settings-group">
                          <div class="dops-card ik-form-has-child">
                            <fieldset class="ik-form-fieldset">
                                <label class="ik-form-label"><span class="ik-form-label-wide"><?php _e('ImageKit URL endpoint (or CNAME)', 'imagekit_domain'); ?></span>
                                  <input id="imagekit_settings[imagekit_url_endpoint]" 
										 type="text" 
										 class="dops-text-input" 
                                         name="imagekit_settings[imagekit_url_endpoint]" 
                                         value="<?php echo isset($imagekit_options['imagekit_url_endpoint']) ? $imagekit_options['imagekit_url_endpoint'] : ''; ?>" />
                                </label>
                                <span class="ik-form-setting-explanation">
									Copy paste the ImageKit URL endpoint (or CNAME) from ImageKit <a href="https://imagekit.io/dashboard#integration" target="_blank">dashboard</a>. 
									<a href="https://docs.imagekit.io/integration/integration-overview#step-2-access-the-image-through-imagekit-io-url-endpoint" target="_blank">Learn more</a>
									
								</span>
							</fieldset>
						  </div>
						</div>
						<div class="ik-form-settings-group">
                          <div class="dops-card ik-form-has-child">
                            <fieldset class="ik-form-fieldset">
								<label class="ik-form-label"><span class="ik-form-label-wide"><?php _e('File types', 'imagekit_domain'); ?></span>
									<input id="imagekit_settings[file_type]" 
										   type="text"
										   name="imagekit_settings[file_type]" 
										   value="<?php echo isset($imagekit_options['file_type']) ? $imagekit_options['file_type'] : ''; ?>" 
										   class="dops-text-input" />
                                </label>
                                <span class="ik-form-setting-explanation">
									Specify the file types that you want to be loaded via ImageKit
								</span>
							</fieldset>
						  </div>
						</div>
						<div class="ik-form-settings-group">
                          <div class="dops-card ik-form-has-child">
                            <fieldset class="ik-form-fieldset">
								<label class="ik-form-label"><span class="ik-form-label-wide"><?php _e('Custom files', 'imagekit_domain');; ?></span>
									<textarea id="imagekit_settings[custom_files]" 
											  name="imagekit_settings[custom_files]"
											  class="dops-text-input"
											  cols="40" 
											  rows="5"><?php echo isset($imagekit_options['custom_files']) ? $imagekit_options['custom_files'] : ''; ?></textarea>
                                </label>
                                <span class="ik-form-setting-explanation">
									Specify any files or directories outside of theme or other common directories to be loaded via ImageKit
								</span>
							</fieldset>
						  </div>
						</div>
						<div class="ik-form-settings-group">
                          <div class="dops-card ik-form-has-child">
                            <fieldset class="ik-form-fieldset">
								<label class="ik-form-label"><span class="ik-form-label-wide"><?php _e('Rejected files', 'imagekit_domain');; ?></span>
									<textarea id="imagekit_settings[reject_files]" 
											  name="imagekit_settings[reject_files]"
											  class="dops-text-input"
											  cols="40" 
											  rows="5"><?php echo isset($imagekit_options['reject_files']) ? $imagekit_options['reject_files'] : ''; ?></textarea>
                                </label>
                                <span class="ik-form-setting-explanation">
									Specify any files or directories that you do not want to load via ImageKit
								</span>
                            </fieldset>
						  </div>
						</div>
						<div class="ik-form-settings-group">
                          <div class="dops-card ik-form-has-child">

							<fieldset class="ik-form-fieldset">
								<label class="ik-form-label"><span class="ik-form-label-wide"><?php _e('Lazy Load Images', 'imagekit_domain');; ?></span></label>
								<p>Lazy loading images will improve your site’s speed and create a smoother viewing experience. Images will load as visitors scroll down the screen, instead of all at once.</p>
								<?php
  $wp_version = (float)get_bloginfo('version');
  if (5.5 <= $wp_version):
?>
									<p>With the release of Version 5.5 of Wordpress Core, <a href="https://wordpress.org/support/wordpress-version/version-5-5/#speed" target="_blank">Lazy-Loading of images</a> has been introduced as a core feature and is enabled by default.</p>

									<?php if (!is_wp_error($api)):
      $main_plugin_file = get_plugin_file($plugin); ?>
										<?php if (check_file_extension($main_plugin_file)): ?>
											<p>We have detected that you are using the <a href="https://wordpress.org/plugins/wp-lazy-loading" target="_blank">Lazy Loading Feature Plugin</a>, you can proceed uninstall it, since it is no longer required.</p>
										   <?php
      endif; ?>
										<?php
    endif; ?>
								<?php
  else: ?>
									<p>For lazy loading, we recommend the <a href="https://wordpress.org/plugins/wp-lazy-loading" target="_blank">Lazy Loading Feature Plugin</a> developed by the WordPress Core Team. This feature has been built into WordPress core since version 5.5 (<a href="https://wordpress.org/support/wordpress-version/version-5-5/#speed" target="_blank">Read More</a>). </p>

									<?php if (!is_wp_error($api)):
      $main_plugin_file = get_plugin_file($plugin); ?>
									<div class="plugin">
									  <div class="plugin-wrap">
										  <img src="<?php echo $api->icons['default']; ?>" alt="">
									   <h2><?php echo $api->name; ?></h2>
									   <p><?php echo $api->short_description; ?></p>

									   <p class="plugin-author"><?php _e('By', 'imagekit_domain'); ?> <?php echo $api->author; ?></p>
									   </div>
									   <ul class="activation-row">
									   <?php if (check_file_extension($main_plugin_file)): ?>
											<?php if (is_plugin_active($main_plugin_file)): ?>
											   <li>
												   <a class="button disabled">Activated</a>
											   </li>
										   <?php
        else: ?>
												<li>
												   <a class="activate button button-primary" href="plugins.php?action=activate&amp;plugin=<?php echo $main_plugin_file ?>&amp;_wpnonce=<?php echo wp_create_nonce('activate-plugin_' . $main_plugin_file) ?>" target="_parent">Activate Plugin</a>
											   </li>
										   <?php
        endif; ?>
									   <?php
      else: ?>
									   <li>
										  <a class="install button"
											href="<?php echo get_admin_url(); ?>/update.php?action=install-plugin&amp;plugin=<?php echo $api->slug; ?>&amp;_wpnonce=<?php echo wp_create_nonce('install-plugin_' . $api->slug) ?>">
											Install Now
										  </a>
									   </li>
									   <?php
      endif; ?>
									   <li>
										  <a href="https://wordpress.org/plugins/<?php echo $api->slug; ?>/" target="_blank">
											 <?php _e('More Details', 'imagekit_domain'); ?>
										  </a>
									   </li>
									</ul>
								   </div>
									<?php
    endif; ?>
								<?php
  endif; ?>
							 </fieldset>
						  </div>
						</div>
						<div class="ik-form-settings-group">
                          <div class="dops-card ik-form-has-child">
							<fieldset class="ik-form-fieldset">
								<label class="ik-form-label">
									<input type="submit" class="button-primary" value="<?php _e('Save changes', 'imagekit_domain'); ?>" />
                                </label>
                                <span class="ik-form-setting-explanation">
									Once you save settings, this plugin will load all post images via ImageKit. If you face any problem, reach out to us at <a href="mailto:support@imagekit.io" target="_blank">support@imagekit.io</a> or <a href="https://docs.imagekit.io/" target="_blank">read docs</a>.
								</span>
                            </fieldset>
                          </div>
                      </div>
                    </form>
                </div>
                
            </div>
			<div class="ik-footer">
				<?php $plugin_data = get_plugin_data(IK_PLUGIN_ENTRYPOINT); ?>
			    <ul class="ik-footer__links">
				    <li class="ik-footer__link-item"><a href="https://imagekit.io/" target="_blank" rel="noopener noreferrer" class="ik-footer__link"><?php echo $plugin_data['Name'] ?> version <?php echo $plugin_data['Version'] ?></a></li>
				</ul>
			</div>
         </div>
      </div>
   </div>
</div>
<?php
  echo ob_get_clean();
}

function imagekit_add_setting_link() {
  add_options_page('ImageKit settings', 'ImageKit settings', 'manage_options', 'imagekit-setting', 'imagekit_render_imagekit_setting_page');
}
add_action('admin_menu', 'imagekit_add_setting_link');

function imagekit_register_settings() {
  add_filter('admin_body_class', function ($classes) {
    $classes .= ' ' . 'imagekit-pagestyles ';
    return $classes;
  });
  register_setting('imagekit_settings_group', 'imagekit_settings');
}

add_action('admin_init', 'imagekit_register_settings');

?>
