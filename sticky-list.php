<?php
/*
Plugin Name: Gravity Forms Sticky List
Plugin URI: https://github.com/13pixlar/sticky-list
Description: List and edit submitted entries from the front end
Version: 1.0.7
Author: 13pixar
Author URI: http://13pixlar.se
*/


/* Todo
 * Support for file uploads
 * Support for GF 1.9 "Save and Continue" functionallity
 * Support for multi page forms
 */

//------------------------------------------
if (class_exists("GFForms")) {
    GFForms::include_addon_framework();

    class StickyList extends GFAddOn {

        protected $_version = "1.0.7";
        protected $_min_gravityforms_version = "1.8.19.2";
        protected $_slug = "sticky-list";
        protected $_path = "gravity-forms-sticky-list/sticky-list.php";
        protected $_full_path = __FILE__;
        protected $_title = "Gravity Forms Sticky List";
        protected $_short_title = "Sticky List";

        public function init(){
            parent::init();

            // 
            $this->stickylist_localize();
            
            
            add_action("gform_field_standard_settings", array( $this, "stickylist_field_settings"), 10, 2);

            
            add_shortcode( 'stickylist', array( $this, 'stickylist_shortcode' ) );

            
            add_action("gform_editor_js", array($this, "editor_script"));

            
            add_filter("gform_tooltips", array( $this, "add_stickylist_tooltips"));

            
            add_action("wp_enqueue_scripts", array( $this, "register_plugin_styles"));

            
            add_action("wp_enqueue_scripts", array( $this, "register_plugin_scripts"));

            
            add_filter("gform_pre_render", array($this,"pre_entry_action"));
            add_action("gform_post_submission", array($this, "post_edit_entry"), 10, 2);

            
            $this->maybe_delete_entry();

            
            add_action("gform_notification_ui_settings", array($this, "stickylist_gform_notification_ui_settings"), 10, 3 );
            add_action("gform_pre_notification_save", array($this, "stickylist_gform_pre_notification_save"), 10, 2 );
            add_filter("gform_disable_notification", array($this, "stickylist_gform_disable_notification" ), 10, 4 );

            
            add_action("gform_confirmation_ui_settings", array($this, "stickylist_gform_confirmation_ui_settings"), 10, 3 );
            add_action("gform_pre_confirmation_save", array($this, "stickylist_gform_pre_confirmation_save"), 10, 2 );
            add_filter("gform_confirmation", array($this, "stickylist_gform_confirmation"), 10, 4);

            
            add_filter("gform_post_data", array( $this, "stickylist_gform_post_data" ), 10, 3 );
        }


        /**
         * Sticky List update Wordpress post
         *
         */
        function stickylist_gform_post_data( $post_data, $form, $entry ) {

            
            if (isset($_POST["post_id"])) $post_data['ID'] = $_POST["post_id"];
            return ( $post_data );
        }

        
        /**
         * Sticky List localization function
         *
         */
        function stickylist_localize() {
            load_plugin_textdomain('sticky-list', false, basename( dirname( __FILE__ ) ) . '/languages' );
        }
        
        
        /**
         * Sticky List field settings function
         *
         */
        function stickylist_field_settings($position, $form_id){

            
            $form = GFAPI::get_form($form_id);

            
            $settings = $this->get_form_settings($form);
                         
            
            if(isset($settings["enable_list"]) && true == $settings["enable_list"]){
                
                
                if($position == -1){ ?>
                    
                    <li class="list_setting">
                        Sticky List
                        <br>
                        <input type="checkbox" id="field_list_value" onclick="SetFieldProperty('stickylistField', this.checked);" /><label class="inline" for="field_list_value"><?php _e('Show in list', 'sticky-list'); ?> <?php gform_tooltip("form_field_list_value") ?></label>
                        <br>
                        <label class="inline" for="field_list_text_value"><?php _e('Column label', 'sticky-list'); ?> <?php gform_tooltip("form_field_text_value") ?></label><br><input class="fieldwidth-3" type="text" id="field_list_text_value" onkeyup="SetFieldProperty('stickylistFieldLabel', this.value);" />  
                    </li>
                    
                    <?php
                }
            }
        }

        
        /**
         * Sticky List field settings JQuery function
         *
         */
        function editor_script(){
            ?>
            <script type='text/javascript'>
                
                jQuery(document).bind("gform_load_field_settings", function(event, field, form){
                    jQuery("#field_list_value").attr("checked", field["stickylistField"] == true);
                    jQuery("#field_list_text_value").val(field["stickylistFieldLabel"]);
                });
            </script>
            <?php
        }

       
        /**
         * Sticky List field settings tooltips function
         *
         */   
        function add_stickylist_tooltips($tooltips){
           $tooltips["form_field_list_value"] = __('<h6>Show field in list</h6>Check this box to show this field in the list.','sticky-list');
           $tooltips["form_field_text_value"] = __('<h6>Header text</h6>Use this field to override the default text header.','sticky-list');
           return $tooltips;
        }

      
        /**
         * Sticky List shortcode function
         *
         */
        function stickylist_shortcode( $atts ) {
            $shortcode_id = shortcode_atts( array(
                'id' => '1',
            ), $atts );

            
            $form_id = $shortcode_id['id'];

            
            $form = GFAPI::get_form($form_id);

            
            function get_sticky_setting($setting_key, $settings) {
                if(isset($settings[$setting_key])) {
                    $setting = $settings[$setting_key];
                }else{
                    $setting = "";
                }
                return $setting;
            }

            
            $settings = $this->get_form_settings($form);

            
            $enable_list            = get_sticky_setting("enable_list", $settings);
            $show_entries_to        = get_sticky_setting("show_entries_to", $settings);
            $enable_view            = get_sticky_setting("enable_view", $settings);
            $enable_view_label      = get_sticky_setting("enable_view_label", $settings);
            $enable_edit            = get_sticky_setting("enable_edit", $settings);
            $enable_edit_label      = get_sticky_setting("enable_edit_label", $settings);
            $enable_delete          = get_sticky_setting("enable_delete", $settings);
            $enable_delete_label    = get_sticky_setting("enable_delete_label", $settings);
            $action_column_header   = get_sticky_setting("action_column_header", $settings);
            $enable_sort            = get_sticky_setting("enable_sort", $settings);
            $enable_search          = get_sticky_setting("enable_search", $settings);
            $embedd_page            = get_sticky_setting("embedd_page", $settings);

            
            if(isset($settings["custom_embedd_page"]) && $settings["custom_embedd_page"] != "") $embedd_page = $settings["custom_embedd_page"];
            
            
            if($enable_list){

                
                $current_user = wp_get_current_user();
                $current_user_id = $current_user->ID;

                
                $sorting = array();
                $paging = array('offset' => 0, 'page_size' => 9999 );

                   
                
                
                if($show_entries_to === "creator"){

                    $search_criteria["field_filters"][] = array("key" => "status", "value" => "active");
                    $search_criteria["field_filters"][] = array("key" => "created_by", "value" => $current_user_id);

                    $entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);
                
                
                }elseif($show_entries_to === "loggedin"){
                    
                    if(is_user_logged_in()) {
                        $search_criteria["field_filters"][] = array("key" => "status", "value" => "active");
                        $entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);
                    }
                
                
                }else{
                
                    $search_criteria["field_filters"][] = array("key" => "status", "value" => "active");
                    $entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);
                }

                
                if(!empty($entries)) {
                    
                    
                    $list_html = "<div id='sticky-list-wrapper'>";
                    
                    
                    if($enable_sort && $enable_search) {
                        $list_html .= "<input class='search' placeholder='" . __("Search", "sticky-list") . "' />";
                    }

                    $list_html .= "<table class='sticky-list'><tr>";
                    
                    
                    $fields = $form["fields"];

                    
                    $i = 0;

                    
                    foreach ($fields as $field) {

                        if(isset($field["stickylistField"]) && $field["stickylistField"] != "") {

                            
                            if(isset($field["stickylistFieldLabel"]) && $field["stickylistFieldLabel"] != "") {                            
                                $label = $field["stickylistFieldLabel"];                                
                            }else{
                                $label = $field["label"];
                            }
                            
                            $list_html .= "<th class='sort' data-sort='sort-$i'>$label</th>";

                            
                            $i++;
                        }
                    }

                    
                    if($enable_view || $enable_edit || $enable_delete) {

                        $list_html .= "<th class='sticky-action'>$action_column_header</th>";
                    }

                    $list_html .= "</tr><tbody class='list'>";

                    
                    foreach ($entries as $entry) {
                        
                        $entry_id = $entry["id"];

                        $list_html .= "<tr>";

                        
                        $i=0;

                        
                        foreach( $form["fields"] as $field ) {

                            
                            if (isset($field["stickylistField"]) && $field["stickylistField"] != "") {
                                
                                
                                $field_value = RGFormsModel::get_lead_field_value( $entry, $field );

                                
                                if(is_array($field_value)) {

                                    
                                    ksort($field_value);
                                   
                                    $field_values = "";

                                    
                                    foreach ($field_value as $field => $value) {
                                        $field_values .= $value . " ";

                                    }
                                    $list_html .= "<td class='sort-$i'>$field_values</td>";

                                }else{ 
                                    $list_html .= "<td class='sort-$i'>$field_value</td>";
                                }

                                
                                $i++;
                            }
                        }

                        
                        if($enable_view || $enable_edit || $enable_delete){
                            
                            $list_html .= "<td class='sticky-action'>";

                                
                                if($enable_view) {
                                    $list_html .= "
                                        <form action='$embedd_page' method='post'>
                                            <button class='submit'>$enable_view_label</button>
                                            <input type='hidden' name='mode' value='view'>
                                            <input type='hidden' name='view_id' value='$entry_id'>
                                        </form>";
                                }

                                
                                if($enable_edit) {

                                    
                                    if($entry["created_by"] == $current_user->ID || current_user_can('edit_others_posts')) {
                                        $list_html .= "
                                            <form action='$embedd_page' method='post'>
                                                <button class='submit'>$enable_edit_label</button>
                                                <input type='hidden' name='mode' value='edit'>
                                                <input type='hidden' name='edit_id' value='$entry_id'>
                                            </form>";
                                    }
                                }

                                
                                if($enable_delete) {

                                    
                                    if($entry["created_by"] == $current_user->ID || current_user_can('delete_others_posts')) {
                                        
                                        $list_html .= "
                                            <button class='sticky-list-delete submit'>$enable_delete_label</button>
                                            <input type='hidden' name='delete_id' class='sticky-list-delete-id' value='$entry_id'>
                                        ";

                                        
                                        if($entry["post_id"] != null ) {
                                            $delete_post_id = $entry["post_id"];
                                            $list_html .= "<input type='hidden' name='delete_post_id' class='sticky-list-delete-post-id' value='$delete_post_id'>";
                                        }
                                    }
                                    ?>
                                    
                                    <?php
                                }

                            $list_html .= "</td>";
                        }

                        $list_html .= "</tr>";
                    }

                    $list_html .= "</tbody></table></div>";

                    
                    if($enable_sort) {

                        
                        $sort_fileds = "";
                        for ($a=0; $a<$i; $a++) { 
                            $sort_fileds .= "'sort-$a',"; 
                        }
                        $list_html .= "<script>var options = { valueNames: [$sort_fileds] };var userList = new List('sticky-list-wrapper', options);</script><br><style>table.sticky-list th:not(.sticky-action) {cursor: pointer;}</style>";
                    }


                    
                    if($enable_delete) {

                        
                        $ajax_delete = plugin_dir_url( __FILE__ ) . 'ajax-delete.php';
                        $ajax_spinner = plugin_dir_url( __FILE__ ) . 'img/ajax-spinner.gif';
                        $delete_failed = __('Delete failed','sticky-list');

                        $list_html .= "
                            <img src='$ajax_spinner' style='display: none;'>
                            <script>
                            jQuery(document).ready(function($) {
                                $('.sticky-list-delete').click(function(event) {
                                    
                                    var delete_id = $(this).siblings('.sticky-list-delete-id').val();
                                    var delete_post_id = $(this).siblings('.sticky-list-delete-post-id').val();
                                    var current_button = $(this);
                                    var current_row = current_button.parent().parent();
                                    current_button.html('<img src=\'$ajax_spinner\'>');
                                    
                                    $.post( '', { mode: 'delete', delete_id: delete_id, delete_post_id: delete_post_id, form_id: '$form_id' })
                                    .done(function() {
                                        current_button.html('');
                                        current_row.css({   
                                            background: '#fbdcdc',
                                            color: '#fff'
                                        });
                                        current_row.hide('slow');
                                    })
                                    .fail(function() {
                                        current_button.html('$delete_failed');
                                    })

                                });
                            });
                            </script>
                        ";
                    }
                
                
                }else{
                    $list_html = $settings["empty_list_text"] . "<br>";
                }
                                    
                return $list_html;
            }
        }
        

        /**
         * Add Sticky List stylesheet
         *
         */
        public function register_plugin_styles() {
            wp_register_style( 'stickylist', plugins_url( 'gravity-forms-sticky-list/css/sticky-list_styles.css' ) );
            wp_enqueue_style( 'stickylist' );
        }


        /**
         * Add Sticky List sortning js (using list.js)
         *
         */
        public function register_plugin_scripts() {
            wp_register_script( 'list-js', plugins_url( 'gravity-forms-sticky-list/js/list.min.js' ) );
            wp_enqueue_script( 'list-js' );

        }


        /**
         * Performs actions when entrys are clicked in the list
         *
         */
        public function pre_entry_action($form) {
            
            if( isset($_POST["mode"]) == "edit" || isset($_POST["mode"]) == "view" ) {

                if($_POST["mode"] == "edit") {
                    $edit_id = $_POST["edit_id"];
                    $form_fields = GFAPI::get_entry($edit_id);
                }

                if($_POST["mode"] == "view") {
                    $view_id = $_POST["view_id"];
                    $form_fields = GFAPI::get_entry($view_id);
                }
        
                
                $current_user = wp_get_current_user();
               
                
                if(!is_wp_error($form_fields) && $form_fields["status"] == "active") {
                    
                    
                    if($form_fields["created_by"] == $current_user->ID || current_user_can('edit_others_posts') || $_POST["mode"] == "view") {
                     
                        
                        foreach ($form_fields as $key => &$value) {

                            
                            if (is_numeric($key)) {

                                
                                if(is_array(maybe_unserialize($value))) {
                                    $list = maybe_unserialize($value);
                                    $value = iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($list)), FALSE);
                                }

                                $new_key = str_replace(".", "_", "input_$key");
                                $form_fields[$new_key] = $form_fields[$key];
                                unset($form_fields[$key]);                                                           
                            }
                        }
                        
                        
                        $form_id = $form['id'];
                        $form_fields["is_submit_$form_id"] = "1";

                        
                        $settings = $this->get_form_settings($form);

                        
                        if(isset($settings["update_text"])) $update_text = $settings["update_text"]; else $update_text = ""; ?>

                        <!-- Add JQuery to help with view/update/delete -->
                        <script>
                        jQuery(document).ready(function($) {
                            var thisForm = $('#gform_<?php echo $form_id;?>')

                <?php   
                        if($_POST["mode"] == "edit") { ?>

                            thisForm.append('<input type="hidden" name="action" value="edit" />');
                            thisForm.append('<input type="hidden" name="original_entry_id" value="<?php echo $edit_id; ?>" />');
                            $("#gform_submit_button_<?php echo $form_id;?>").val('<?php echo $update_text; ?>');

                <?php   }

                        
                        if($_POST["mode"] == "view") { ?>

                            $("#gform_<?php echo $form_id;?> :input").attr("disabled", true);
                            $("#gform_submit_button_<?php echo $form_id;?>").css('display', 'none');
                <?php   }

                        
                        if($form_fields["post_id"] != null ) { ?>

                            thisForm.append('<input type="hidden" name="post_id" value="<?php echo $form_fields["post_id"];?>" />');
                <?php   } ?>

                        });
                        </script>
                        <!-- End JQuery -->

                <?php   
                        $_POST = $form_fields;
                    }
                }
            }
            
            return $form;
        }


        /**
         *  Editing entries
         *
         */ 
        public function post_edit_entry($entry, $form) {
            
            
            if(isset($_POST["action"]) && $_POST["action"] == "edit") {

                
                $original_entry_id = $_POST["original_entry_id"];

                
                $current_user = wp_get_current_user();
                
                
                $original_entry =  GFAPI::get_entry($original_entry_id);

                
                if($original_entry && $original_entry["status"] == "active") {

                    
                    if($original_entry["created_by"] == $current_user->ID || current_user_can('edit_others_posts')) {

                        
                        $entry["is_read"] = $original_entry["is_read"];
                        $entry["is_starred"] = $original_entry["is_starred"];

                        
                        $success_uppdate = GFAPI::update_entry($entry, $original_entry_id);
                        
                        
                        if($success_uppdate) $success_delete = GFAPI::delete_entry($entry["id"]);
                    }
                }
            }
        }


        /**
         * Delete entries
         * This function is used to delete entries with an ajax request
         * Could use better (or at least some) error handling
         */
        public function maybe_delete_entry() {
            
            
            if(isset($_POST["mode"]) && $_POST["mode"] == "delete" && isset($_POST["delete_id"]) && isset($_POST["form_id"])) {

                
                $form_id = $_POST["form_id"];

                
                $form = GFAPI::get_form($form_id);

                
                $settings = $this->get_form_settings($form);
                $enable_delete = $settings["enable_delete"];
                $delete_type = $settings["delete_type"];

                
                if($enable_delete) {

                    $delete_id = $_POST["delete_id"];                
                    $current_user = wp_get_current_user();
                    $entry = GFAPI::get_entry($delete_id);
                    
                    
                    if(!is_wp_error($entry)) {

                        
                        if($entry["created_by"] == $current_user->ID || current_user_can('delete_others_posts' )) {

                            
                            if($_POST["delete_post_id"] != null) {
                                $delete_post_id = $_POST["delete_post_id"];
                            }else{
                                $delete_post_id = "";
                            }
                           
                            
                            if($delete_type == "trash") { 
                                $entry["status"] = "trash";
                                $success = GFAPI::update_entry($entry, $delete_id);

                                
                                if($delete_post_id != "") {
                                    wp_delete_post( $delete_post_id, false );
                                }
                            }

                            
                            if($delete_type == "permanent") {
                                $success = GFAPI::delete_entry($delete_id);

                                
                                if($delete_post_id != "") {
                                     wp_delete_post( $delete_post_id, true );
                                }
                            }

                            
                            if($success) {

                                
                                $notifications = $form["notifications"];
                                $notification_ids = array();
                                
                                
                                foreach ($notifications as $notification) {

                                    
                                    $notification_type = $notification["stickylist_notification_type"];

                                    
                                    if($notification_type == "delete" || $notification_type == "all") {
                                        $id = $notification["id"];
                                        array_push($notification_ids, $id);        
                                    }
                                }
                                
                                
                                GFCommon::send_notifications($notification_ids, $form, $entry);
                            }          
                        }
                    }
                }
            }
        }


        /**
         * Form settings page
         *
         */
        public function form_settings_fields($form) {
            ?>
            <script>
            
            jQuery(document).ready(function($) { 
                $('#gaddon-setting-row-header-0 h4').html('<?php _e("General settings","sticky-list"); ?>')
                $('#gaddon-setting-row-header-1 h4').html('<?php _e("View, edit & delete","sticky-list"); ?>')
                $('#gaddon-setting-row-header-2 h4').html('<?php _e("Labels","sticky-list"); ?>')
                $('#gaddon-setting-row-header-3 h4').html('<?php _e("Sort & search","sticky-list"); ?>')
                $('#gaddon-setting-row-header-4 h4').html('<?php _e("Donate","sticky-list"); ?>')
                $('#gaddon-setting-row-donate .donate-text').html('<?php _e("Sticky List is completely free. But if you like, you can always <a target=\"_blank\" href=\"https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8R393YVXREFN6\">donate</a> a few bucks.","sticky-list"); ?>')
             });
            </script>
            <?php

            
            $args = array( 'posts_per_page' => 999999, 'post_type' => 'any', 'post_status' => 'any', 'orderby' => 'title'); 
            $posts = get_posts( $args );
            $posts_array = array();
            foreach ($posts as $post) {
                $post_title = get_the_title($post->ID);
                $post_url = get_permalink($post->ID);

                
                if($post->post_type != 'attachment') {
                    $posts_array = array_merge(
                        array(
                            array(
                                "label" => $post_title,
                                "value" => $post_url
                            )
                        ),$posts_array);
                }
            }
            
            return array(
                array(
                    "title"  => __('Sticky List Settings','sticky-list'),
                    "fields" => array(
                        array(
                            "label"   => __('Enable for this form','sticky-list'),
                            "type"    => "checkbox",
                            "name"    => "enable_list",
                            "tooltip" => __('Check this box to enable Sticky List for this form','sticky-list'),
                            "choices" => array(
                                array(
                                    "label" => "",
                                    "name"  => "enable_list"
                                )
                            )
                        ),
                        array(
                            "label"   => __('Show entries in list to','sticky-list'),
                            "type"    => "select",
                            "name"    => "show_entries_to",
                            "tooltip" => __('Who should be able to se the entries in the list?','sticky-list'),
                            "choices" => array(
                                array(
                                    "label" => __('Entry creator','sticky-list'),
                                    "value" => "creator"
                                ),
                                array(
                                    "label" => __('All logged in users','sticky-list'),
                                    "value" => "loggedin"
                                ),
                                array(
                                    "label" => __('Everyone','sticky-list'),
                                    "value" => "everyone"
                                )
                            )
                        ),
                        array(
                            "label"   => __('Embedd page/post','sticky-list'),
                            "type"    => "select",
                            "name"    => "embedd_page",
                            "tooltip" => __('The page/post where the form is embedded. This page will be used to view/edit the entry','sticky-list'),
                            "choices" => $posts_array
                        ),
                        array(
                            "label"   => __('Custom url','sticky-list'),
                            "type"    => "text",
                            "name"    => "custom_embedd_page",
                            "tooltip" => __('Manually input the url of the form. This overrides the selection made in the dropdown above. Use this if you cannot find the page/post in the list.','sticky-list'),
                            "class"   => "medium"
                        ),
                        array(
                            "label"   => __('View entries','sticky-list'),
                            "type"    => "checkbox",
                            "name"    => "enable_view",
                            "tooltip" => __('Check this box to enable users to view the complete submitted entry. A \"View\" link will appear in the list','sticky-list'),
                            "choices" => array(
                                array(
                                    "label" => __('Enabled','sticky-list'),
                                    "name"  => "enable_view"
                                )
                            )
                        ),
                        array(
                            "label"   => __('View label','sticky-list'),
                            "type"    => "text",
                            "name"    => "enable_view_label",
                            "tooltip" => __('Label for the view button','sticky-list'),
                            "class"   => "small",
                            "default_value" => __('View','sticky-list')
                            
                        ),
                        array(
                            "label"   => __('Edit entries','sticky-list'),
                            "type"    => "checkbox",
                            "name"    => "enable_edit",
                            "tooltip" => __('Check this box to enable user to edit submitted entries. An \"Edit\" link will appear in the list','sticky-list'),
                            "choices" => array(
                                array(
                                    "label" => __('Enabled','sticky-list'),
                                    "name"  => "enable_edit"
                                )
                            )
                        ),
                        array(
                            "label"   => __('Edit label','sticky-list'),
                            "type"    => "text",
                            "name"    => "enable_edit_label",
                            "tooltip" => __('Label for the edit button','sticky-list'),
                            "class"   => "small",
                            "default_value" => __('Edit','sticky-list')
                            
                        ),
                         array(
                            "label"   => __('Update button text','sticky-list'),
                            "type"    => "text",
                            "name"    => "update_text",
                            "tooltip" => __('Text for the submit button that is displayed when editing an entry','sticky-list'),
                            "class"   => "small",
                            "default_value" => __('Update','sticky-list')              
                        ),
                        array(
                            "label"   => __('Delete entries','sticky-list'),
                            "type"    => "checkbox",
                            "name"    => "enable_delete",
                            "tooltip" => __('Check this box to enable user to delete submitted entries. A \"Delete\" link will appear in the list','sticky-list'),
                            "choices" => array(
                                array(
                                    "label" => __('Enabled','sticky-list'),
                                    "name"  => "enable_delete"
                                )
                            )
                        ),
                        array(
                            "label"   => __('Delete label','sticky-list'),
                            "type"    => "text",
                            "name"    => "enable_delete_label",
                            "tooltip" => __('Label for the delete button','sticky-list'),
                            "class"   => "small",
                            "default_value" => __('Delete','sticky-list')
                        ),
                        array(
                            "label"   => __('On delete','sticky-list'),
                            "type"    => "select",
                            "name"    => "delete_type",
                            "tooltip" => __('Move deleted entries to trash or delete permanently?','sticky-list'),
                            "choices" => array(
                                array(
                                    "label" => __('Move to trash','sticky-list'),
                                    "value" => "trash"
                                ),
                                array(
                                    "label" => __('Delete permanently','sticky-list'),
                                    "value" => "permanent"
                                )
                            )
                        ),
                        array(
                            "label"   => __('Action column header','sticky-list'),
                            "type"    => "text",
                            "name"    => "action_column_header",
                            "tooltip" => __('Text to show as header for the action column','sticky-list'),
                            "class"   => "medium"
                            
                        ),
                        array(
                            "label"   => __('Empty list text','sticky-list'),
                            "type"    => "text",
                            "name"    => "empty_list_text",
                            "tooltip" => __('Text that is shown if the list is empty','sticky-list'),
                            "class"   => "medium",
                            "default_value" => __('The list is empty. You can edit or remove this text in settings','sticky-list')
                        ),
                        array(
                            "label"   => __('List sort','sticky-list'),
                            "type"    => "checkbox",
                            "name"    => "enable_sort",
                            "tooltip" => __('Check this box to enable sorting for the list','sticky-list'),
                            "choices" => array(
                                array(
                                    "label" => __('Enabled','sticky-list'),
                                    "name"  => "enable_sort"
                                )
                            )
                        ),
                        array(
                            "label"   => __('List search','sticky-list'),
                            "type"    => "checkbox",
                            "name"    => "enable_search",
                            "tooltip" => __('Check this box to enable search for the list','sticky-list'),
                            "choices" => array(
                                array(
                                    "label" => __('Enabled','sticky-list'),
                                    "name"  => "enable_search"
                                )
                            )
                        )
                    )
                )
            );
        }


        /**
         * Include admin scripts
         *
         */
        public function scripts() {
        $scripts = array(
            array("handle" => "sticky_list_js",
                "src" => $this->get_base_url() . "/js/sticky-list_scripts.js",
                "version" => $this->_version,
                "deps" => array("jquery"),
                "enqueue" => array(
                    array(
                        "admin_page" => array("form_settings"),
                        "tab" => "sticky-list"
                        )
                    )
                ),
            );
            return array_merge(parent::scripts(), $scripts);
        }


        /**
         * Include admin css
         *
         */
        public function styles() {
            $styles = array(
                array("handle" => "sticky-list_admin_styles",
                    "src" => $this->get_base_url() . "/css/sticky-list_admin_styles.css",
                    "version" => $this->_version,
                    "enqueue" => array(
                    array(
                        "admin_page" => array("form_settings"),
                        "tab" => "sticky-list"
                        )
                    )
                )
            );
            return array_merge(parent::styles(), $styles);
        }


        /**
         * Add new notification settings
         *
         */
        function stickylist_gform_notification_ui_settings( $ui_settings, $notification, $form ) {

            $settings = $this->get_form_settings($form);

            if (isset($settings["enable_list"])) {

                
                $type = rgar( $notification, 'stickylist_notification_type' );
                $options = array(
                    'all' => __( "Always", 'sticky-list' ),
                    'new' => __( "When a new entry is submitted", 'sticky-list' ),
                    'edit' => __( "When an entry is updated", 'sticky-list' ),
                    'delete' => __( "When an entry is deleted", 'sticky-list' )
                );

                $option = '';

                
                foreach ( $options as $key => $value ) {
                    
                    $selected = '';
                    if ( $type == $key ) $selected = ' selected="selected"';
                    $option .= "<option value=\"{$key}\" {$selected}>{$value}</option>\n";
                }

                
                $ui_settings['sticky-list_notification_setting'] = '
                <tr>
                    <th><label for="stickylist_notification_type">' . __( "Send this notification", 'sticky-list' ) . '</label></th>
                    <td><select name="stickylist_notification_type" value="' . $type . '">' . $option . '</select></td>
                </tr>';              
            }  

            return ( $ui_settings );
        }


        /**
         * Save the notification settings
         *
         */
        function stickylist_gform_pre_notification_save($notification, $form) {

            $notification['stickylist_notification_type'] = rgpost( 'stickylist_notification_type' );
            return ( $notification );
        }


        /**
         * Send selected notification type
         *
         */
        function stickylist_gform_disable_notification( $is_disabled, $notification, $form, $entry ) {

            
            $settings = $this->get_form_settings($form);

            
            if(isset($settings["enable_list"])) {
                
                if(isset($notification["stickylist_notification_type"]) && $notification["stickylist_notification_type"] != "") {

                    $is_disabled = true;

                    
                    if($_POST["action"] == "edit") {
                        
                        
                        if($notification["stickylist_notification_type"] == "edit" || $notification["stickylist_notification_type"] == "all") {
                            $is_disabled = false;
                        }

                    
                    }else{
                        
                        
                        if ( $notification["stickylist_notification_type"] == "new" || $notification["stickylist_notification_type"] == "all" ) {
                            $is_disabled = false;
                        }
                    }
                }           
            }

            return ( $is_disabled );
        }


        /**
         * Add new confirmation settings
         *
         */
        function stickylist_gform_confirmation_ui_settings( $ui_settings, $confirmation, $form ) {

            $settings = $this->get_form_settings($form);

            if (isset($settings["enable_list"])) {

                
                $type = rgar( $confirmation, 'stickylist_confirmation_type' );
               
                $options = array(
                    'all' => __( "Always", 'sticky-list' ),
                    'never' => __( "Never", 'sticky-list' ),
                    'new' => __( "When a new entry is submitted", 'sticky-list' ),
                    'edit' => __( "When an entry is updated", 'sticky-list' ),
                );

                $option = '';

                
                foreach ( $options as $key => $value ) {
                    
                    $selected = '';
                    if ( $type == $key ) $selected = ' selected="selected"';
                    $option .= "<option value=\"{$key}\" {$selected}>{$value}</option>\n";
                }

                
                $ui_settings['sticky-list_confirmation_setting'] = '
                <tr>
                    <th><label for="stickylist_confirmation_type">' . __( "Display this confirmation", 'sticky-list' ) . '</label></th>
                    <td><select name="stickylist_confirmation_type" value="' . $type . '">' . $option . '</select></td>
                </tr>';  
            }

            return ( $ui_settings );  
        }


        /**
         * Save the confirmation settings
         *
         */
        function stickylist_gform_pre_confirmation_save($confirmation, $form) {

            $confirmation['stickylist_confirmation_type'] = rgpost( 'stickylist_confirmation_type' );
            return ( $confirmation );
        }


        /**
         * Show confirmations
         *
         */
        function stickylist_gform_confirmation($original_confirmation, $form, $lead, $ajax){

            
            $settings = $this->get_form_settings($form);

            
            if(isset($settings["enable_list"])) {
            
                
                $confirmations = $form["confirmations"];
                $new_confirmation = "";

                
                if(!isset($_POST["action"])) {
                    $_POST["action"] = "new";
                }

                
                foreach ($confirmations as $confirmation) {

                    
                    if (isset($confirmation["stickylist_confirmation_type"])) {
                        $confirmation_type = $confirmation["stickylist_confirmation_type"];
                    }else{
                        $confirmation_type = "";
                    }

                    
                    if( $confirmation_type == $_POST["action"] || $confirmation_type == "all" || !isset($confirmation["stickylist_confirmation_type"])) {
                        
                        
                        if($confirmation["type"] == "message") {
                            $new_confirmation .= $confirmation["message"] . " ";

                        
                        }else{
                            $new_confirmation = $original_confirmation;
                            break;
                        }
                    }             
                }

                
                $new_confirmation = GFCommon::replace_variables($new_confirmation, $form, $lead);

                return $new_confirmation;

            }else{

                
                return $original_confirmation;
            }

        }
    }

    
    new StickyList();
}
