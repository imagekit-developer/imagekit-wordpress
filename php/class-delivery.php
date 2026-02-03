<?php

namespace ImageKitWordpress;

use ImageKitWordpress\Component\Setup;

class Delivery implements Setup {

    protected $plugin;

    protected $media;

    public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;
		add_action( 'cloudinary_connected', array( $this, 'init' ) );
	}

    public function init() {
        $this->media                         = $this->plugin->get_component( 'media' );


    }

    public function setup() {


		
	}
}