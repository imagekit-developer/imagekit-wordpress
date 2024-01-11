=== ImageKit - URL based image manipulation and optimization ===
Contributors: imagekit
Donate link: 
Tags: images,image management, image manipulation, image optimization, image optimisation,imagekit, wepb,photo, photos, picture, pictures, thumbnail, thumbnails, upload, batch, cdn, content delivery network
Requires at least: 3.3
Tested up to: 6.4.2
Stable tag: 4.1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Faster & lighter experience for your users. Deliver optimized images on all platforms instantly using ImageKit.

== Description ==

Images make up a critical part of all websites and mobile applications these days. They are the centerpieces of a great product and user experience. Managing your images and delivering the perfect image, tailored and optimized for your user’s device is, therefore, more critical than it has ever been. However, this takes up a lot of development and maintenance time that could have otherwise been used in building your core product. This is where ImageKit can excel.

This plugin will **automatically update all the image URLs in your post** so that images are fetched from ImageKit for optimization and faster delivery instead of your web server.

= Gets the best out of all your images in less than 10 minutes =

* Your existing images get all the benefits instantly.
* Size, quality & format optimizations work automatically.
* URL-based image transformations like resize, crop, rotate etc.
* Responsive images for a tailored experience across devices.
* Up to 50% load time reduction with quality and format settings.
* CDN-powered delivery of images across the globe.
* Simple dashboard to monitor usage and manage your images.
* Easy to integrate SDKs for uploads and other features.

= Requirements =

You just need to [Create an account](https://imagekit.io/registration) on ImageKit to use this plugin and get optimization benefits on your WordPress website instantly.

= About ImageKit =

* [Main website](https://imagekit.io)
* [Website analyzer](https://imagekit.io/website-analyzer)
* [Features](https://imagekit.io/features)
* [Help center](https://help.imagekit.io)
* [Developer documentation](https://docs.imagekit.io)
* [Blog](https://imagekit.io/blog)

= Support =

* Support Email: [developer@imagekit.io](developer@imagekit.io)


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->ImageKit setting screen to configure the plugin. Check out [WordPress integration guide](https://help.imagekit.io/integrating-imagekit/quick-integration/integrate-imagekit-on-wordpress-website)

== Frequently asked questions ==

= Do I have to register on ImageKit.io to use this module? =

Yes, you need to [create an account](https://imagekit.io/registration) on https://imagekit.io first to use this plugin.

= How does this plugin works? =

This plugin changes the HTML content of the post to replace base URL with ImageKit endpoint so that images are loaded via ImageKit.

= I installed the plugin but Google pagespeed insights is still showing image related warnings =

This plugin automatically optimize the images and serve them in next-gen format including WebP. However, this plugin does not automatically resize the images as per the layout. WordPress 4.4 has [added native support for responsive image](https://make.wordpress.org/core/2015/11/10/responsive-images-in-wordpress-4-4/). [Learn more](https://viastudio.com/optimizing-your-theme-for-wordpress-4-4s-responsive-images/) to make your themes image responsive.

= Do I have to manually change the old posts to optimize their images? =

No, this plugin automatically takes care of that.

= Does this plugin support custom CNAME? =

Yes, you can email developer@imagekit.io to configure custom CNAME for your account and then specify that in the plugin setting page.

= Can I configure this plugin to use ImageKit for custom upload directories? =

Yes, you can specify any number of custom directory locations on plugin settings page

= Does ImageKit support all image formats? =

ImageKit supports all popular image formats that cover 99.99% of the use case. On the settings page, you can further configure if you want to allow or disallow a particular file type to be loaded via ImageKit.

= I installed the plugin, but the ImageKit website analyzer is suggesting more optimization. =

This is because image dimensions are not as per the layout. We could have done it using Javascript in the frontend like other plugins, but we do not recommend it. The browser triggers the image load as soon as it sees an image URL in HTML and intentionally delaying this while Javascript calculates the ideal width will ultimately slow down the image load for your users. WordPress 4.4 has [added native support for a responsive image](https://make.wordpress.org/core/2015/11/10/responsive-images-in-wordpress-4-4/). [Learn more](https://viastudio.com/optimizing-your-theme-for-wordpress-4-4s-responsive-images/) to make your themes image responsive.


== Screenshots ==

1

== Changelog ==

4.1.3
Tested on Wordpress 6.4.2

4.1.2
Bug Fixes

4.1.1
Bug Fixes and tested on Wordpress 5.7.2

4.1.0
Bug Fixes. Tested for PHP 8 and Wordpress 5.6

4.0.1
Bug Fixes and Lazy Load Update

4.0.0
Bug Fixes and Added Lazy Load support

3.0.5
Modified the Registration message on settings page

3.0.4
Updated readme

3.0.2
Bug fix

3.0.1
A bug fix for checking value of imagekit_id

3.0.0
Added and updated documentation links. Simplified the integration steps.

2.0.7
Stopped transforming image urls to use ImageKit's resizing transformations.

2.0.6
Added CNAME support

== Upgrade notice ==

Updated integration steps and documentation links.

