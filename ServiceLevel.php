<?php

class ServiceLevelPlugin extends MantisPlugin {
	function register() {
		$this->name = 'Service Level Plugin';    # Proper name of plugin
		$this->description = 'Currently only advanced Evaluation of Issues';    # Short description of the plugin
		$this->page = '';           # Default plugin page

		$this->version = '0.2';     # Plugin version string
		$this->requires = array(    # Plugin dependencies, array of basename => version pairs
            'MantisCore' => '1.2',  #   Should always depend on an appropriate version of MantisBT
		);

		$this->author = 'Alexander Menk';         # Author/team name
		$this->contact = 'via github';        # Author/team e-mail address
		$this->url = '';            # Support webpage
	}

	function hooks() {
		return array(
            'EVENT_MENU_MAIN' => 'menu'
		);
	}
	
	function menu() {
		if ( access_has_project_level( config_get( 'view_summary_threshold' ) ) ) 
			return array('<a href="' . plugin_page( 'evaluation_page' ) . '">' . plugin_lang_get( 'link' ) . '</a>');
		else
			return null;
	}

}

