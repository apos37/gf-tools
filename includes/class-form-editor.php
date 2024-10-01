<?php
/**
 * Form editor class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Form_Editor {

    /**
     * Store the plugin settings here for the rest of the stuff
     *
     * @var array
     */
    public $plugin_settings = [];


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings ) {

        // Update the plugin settings
        $this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];

        // Always Bypass Template Library when Creating New Forms
        if ( isset( $plugin_settings[ 'bypass_template_library' ] ) && $plugin_settings[ 'bypass_template_library' ] == 1 ) {
            add_filter( 'gform_bypass_template_library', '__return_true' );
        }

        // Disable AJAX Saving for All Forms
        if ( isset( $plugin_settings[ 'ajax_saving' ] ) && $plugin_settings[ 'ajax_saving' ] == 1 ) {
            add_filter( 'gform_disable_ajax_save', '__return_true' );
        }

        // Move United States to Top of Countries List on Address Fields
        if ( isset( $plugin_settings[ 'united_states_first' ] ) && $plugin_settings[ 'united_states_first' ] == 1 ) {
            add_action( 'gform_countries', [ $this, 'united_states_first' ] );
        }

        // Add Associated States to US States List
        if ( isset( $plugin_settings[ 'associated_states' ] ) && $plugin_settings[ 'associated_states' ] == 1 ) {
            add_action( 'gform_us_states', [ $this, 'associated_states' ] );
        }

        // Disable Post Meta Query on Form Editor
        if ( isset( $plugin_settings[ 'post_meta_query' ] ) && $plugin_settings[ 'post_meta_query' ] == 1 ) {
            add_filter( 'gform_disable_custom_field_names_query', '__return_true' );
        }
        
        // Remove "Add Field" Buttons
        $add_fields_to_remove = [ 'section', 'file_upload', 'post_section', 'pricing_section' ];
        $incl_add_fields_to_remove = false;
        foreach ( $add_fields_to_remove as $aftr ) {
            if ( isset( $plugin_settings[ 'remove_'.$aftr ] ) && $plugin_settings[ 'remove_'.$aftr ] == 1 ) {
                $incl_add_fields_to_remove = true;
                break;
            }
        }
        if ( $incl_add_fields_to_remove ) {
            add_action( 'gform_add_field_buttons', [ $this, 'remove_add_field_buttons' ] );
        }

        // Add Additional Classes to the Submit Buttons
        if ( isset( $plugin_settings[ 'submit_button_classes' ] ) && $plugin_settings[ 'submit_button_classes' ] != '' ) {
            add_filter( 'gform_submit_button', [ $this, 'submit_button_classes' ], 10, 2 );
        }

        // Quiz answers side panel
        if ( is_plugin_active( 'gravityformsquiz/quiz.php' ) ) {
            add_filter( 'gform_editor_sidebar_panels', [ $this, 'editor_sidebar_panels' ], 10, 2 );
            add_action( 'gform_editor_sidebar_panel_content_gfadvtools_quiz_answers', [ $this, 'editor_sidebar_panel_content' ], 10, 2 );
        }

        // Field visibility options
        // TODO: In a future version
        // add_filter( 'gform_visibility_options', [ $this, 'visibility_options' ] );

        // Add extra settings
        add_action( 'gform_field_standard_settings', [ $this, 'standard_settings' ], 10, 2 );
        add_action( 'gform_field_appearance_settings', [ $this, 'appearance_settings' ], 10, 2 );
        add_action( 'gform_field_advanced_settings', [ $this, 'advanced_settings' ], 10, 2 );
        add_filter( 'gform_tooltips', [ $this, 'tooltips' ] );
        add_action( 'gform_editor_js', [ $this, 'editor_script_field_settings' ] );

        // Custom field tab
        // TODO: In a future version as an alternative
        // add_filter( 'gform_field_settings_tabs', [ $this, 'custom_field_tab' ], 10, 2 );
        // add_action( 'gform_field_settings_tab_content_gfadvtools', [ $this, 'custom_field_tab_content' ], 10, 2 );

        // Add a custom field type
        // add_filter( 'gform_field_groups_form_editor', [ $this, 'field_groups_form_editor' ], 10, 1 );

	} // End __construct()


    /**
     * Move United States to Top of Countries List on Address Fields
     *
     * @param array $countries
     * @return array
     */
    public function united_states_first( $countries ) {
        if ( $index = array_search( 'United States', $countries ) ) {
            unset( $countries[ $index ] );
            $countries = array_values( $countries );
            array_unshift( $countries, 'United States' );
        }
        return $countries;
    } // End united_states_first()


    /**
     * Add Associated States to US States List
     *
     * @param array $states
     * @return array
     */
    public function associated_states( $states ) {
        $territories = [
            'Federated States of Micronesia',
            'Marshall Islands',
            'Palau',
        ];

        $states = array_merge( $states, $territories ); 
        sort( $states );

        return $states;
    } // End associated_states()


    /**
     * Remove "Add Field" Buttons
     *
     * @param array $states
     * @return array
     */
    public function remove_add_field_buttons( $field_groups  ) {
        $index                = 0;
        $standard_field_index = - 1;
        $advanced_field_index = - 1;
        $post_field_index     = - 1;
        $pricing_field_index  = - 1;

        // Finding group indexes
        foreach ( $field_groups as $group ) {
            if ( $group[ 'name' ] == 'standard_fields' ) {
                $standard_field_index = $index;
            } elseif ( $group[ 'name' ] == 'advanced_fields' ) {
                $advanced_field_index = $index;
            } elseif ( $group[ 'name' ] == 'post_fields' ) {
                $post_field_index = $index;
            } elseif ( $group[ 'name' ] == 'pricing_fields' ) {
                $pricing_field_index = $index;
            } 
    
            $index ++;
        }

        // Section Field
        if ( isset( $this->plugin_settings[ 'remove_section' ] ) && $this->plugin_settings[ 'remove_section' ] == 1 ) {
            if ( $standard_field_index >= 0 ) {
                $section_field_index = - 1;
                $index             = 0;
                foreach ( $field_groups[ $standard_field_index ][ 'fields' ] as $standard_field ) {
                    if ( $standard_field[ 'value' ] == 'Section' ) {
                        $section_field_index = $index;
                    }
                    $index ++;
                }
         
                unset( $field_groups[ $standard_field_index ][ 'fields' ][ $section_field_index ] );
            }
        }

        // File Upload Field
        if ( isset( $this->plugin_settings[ 'remove_file_upload' ] ) && $this->plugin_settings[ 'remove_file_upload' ] == 1 ) {
            if ( $advanced_field_index >= 0 ) {
                $file_upload_index = - 1;
                $index             = 0;
                foreach ( $field_groups[ $advanced_field_index ][ 'fields' ] as $advanced_field ) {
                    if ( $advanced_field[ 'value' ] == 'File Upload' ) {
                        $file_upload_index = $index;
                    }
                    $index ++;
                }
         
                unset( $field_groups[ $advanced_field_index ][ 'fields' ][ $file_upload_index ] );
            }
        }

        // All Post Fields
        if ( isset( $this->plugin_settings[ 'remove_post_section' ] ) && $this->plugin_settings[ 'remove_post_section' ] == 1 ) {
            if ( $post_field_index >= 0 ) {
                unset( $field_groups[ $post_field_index ] );
            }
        }

        // All Pricing Fields
        if ( isset( $this->plugin_settings[ 'remove_pricing_section' ] ) && $this->plugin_settings[ 'remove_pricing_section' ] == 1 ) {
            if ( $pricing_field_index >= 0 ) {
                unset( $field_groups[ $pricing_field_index ] );
            }
        }

        return $field_groups;
    } // End remove_add_field_buttons()


    /**
     * Add Additional Classes to the Submit Buttons
     *
     * @param string $button
     * @param [type] $form
     * @return string
     */
    public function submit_button_classes( $button, $form ) {
        $dom = new DOMDocument();
        $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $button );
        $input = $dom->getElementsByTagName( 'input' )->item(0);
        $classes = $input->getAttribute( 'class' );
        $classes .= ' '.sanitize_text_field( $this->plugin_settings[ 'submit_button_classes' ] );
        $input->setAttribute( 'class', $classes );
        return $dom->saveHtml( $input );
    } // End submit_button_classes()


    /**
     * Add quiz answers to editor side panel
     *
     * @param array $panels
     * @param array $form
     * @return array
     */
    public function editor_sidebar_panels( $panels, $form ) {
        $has_quiz_fields = false;
        foreach ( $form[ 'fields' ] as $field ) {
            if ( $field->type == 'quiz' ) {
                $has_quiz_fields = true;
                break;
            }
        }
        if ( $has_quiz_fields ) {
            $panels[] = [
                'id'           => 'gfadvtools_quiz_answers',
                'title'        => 'Answers',
                'nav_classes'  => [ 'gfadvtools_quiz_answers_1', 'gfadvtools_quiz_answers_2' ],
                'body_classes' => [ 'gfadvtools_quiz_answers' ],
            ];
        }
        return $panels;
    } // End editor_sidebar_panels()


    /**
     * Quiz answer side panel content
     *
     * @param array $panel
     * @param array $form
     * @return void
     */
    public function editor_sidebar_panel_content( $panel, $form ) {
        $has_quiz_fields = false;
        foreach ( $form[ 'fields' ] as $field ) {
            if ( $field->type == 'quiz' ) {
                $has_quiz_fields = true;
                break;
            }
        }
        if ( $has_quiz_fields ) {
            echo '<div id="gfadvtools_quiz_answers_panel">
                <div id="gfadvtools_quiz_answers_body">
                    <div class="gfat-quiz-answers">Quiz Answers</div>
                    <p>Please save the form and refresh to see updated answers.</p>';

                    $correct_answers = [];
                    $alphabet = range( 'A', 'Z' );

                    foreach ( $form[ 'fields' ] as $field ) {
                        if ( $field[ 'type' ] != 'quiz' ) {
                            continue;
                        }

                        $choices = $field[ 'choices' ];

                        $answers = [];
                        foreach ( $choices as $num => $choice ) {
                            if ( isset( $choice[ 'gquizIsCorrect' ] ) && $choice[ 'gquizIsCorrect' ] == 1 ) {
                                $answers[] = '<strong>'.$alphabet[ $num ].'</strong> ('.round( $num + 1, 0 ).') - '.$choice[ 'text' ];
                            }
                        }
                        if ( empty( $answers ) ) {
                            $answers[] = 'N/A';
                        }

                        $correct_answers[] = '<span class="gfat-question"><strong>'.$field[ 'label' ].'</strong></span><br><span class="gfat-correct-answers">'.implode( '<br>', $answers ).'</span>';
                    }

                    // Add the answers
                    if ( !empty( $correct_answers ) ) {
                        echo '<ol>';

                        foreach ( $correct_answers as $key => $ca ) {
                            $key++;
                            echo '<li><span class="num">'.esc_attr( $key ).'. </span>'.wp_kses( $ca, [ 'strong' => [], 'br' => [], 'span' => [ 'class' => [] ] ] ).'<br><br></li>';
                        }

                        echo '</ol>';
                    } else {
                        echo '<strong><em>No Correct Answers Found.</em></strong><br><br>Note: answers are only provided for Quiz fields, <em>not</em> regular radio, checkbox or dropdown fields.';
                    }

                // End body
                echo '</div>';

            // End the panel body
            echo '</div>';
        }
    } // End editor_sidebar_panel_content()


    /**
     * Add additional roles to visibility options
     *
     * @param array $options
     * @return array
     */
    public function visibility_options( $options ) {
        $roles = get_editable_roles();
        ksort( $roles );

        foreach ( $roles as $slug => $role ) {
            if ( $slug == 'administrator' ) {
                continue;
            }
            $options[] = [
                'label'       => $role[ 'name' ],
                'value'       => $slug,
                // 'description' => ''
            ];
        }
        return $options;
    } // End visibility_options()


    /**
     * Get the fields we want to add
     * parameters: [
     *  'section'   // the tab
     *  'position'  // the position as noted here: https://docs.gravityforms.com/category/developers/php-api/field-framework/field-framework-settings/
     *  'type'      // the field type
     *  'fields'    // an array of fields to display this option on, default is all fields
     *  'id'        // a unique id for this field
     *  'label      // the field label
     *  'tooltip'   // the tooltip for this field
     *  'merge_tag' // whether to include a merge tag drop down, default to false
     * ]
     *
     * @return array
     */
    public function get_fields() {
        return [
            [
                'section'       => 'advanced_settings',
                'position'      => -1,
                'type'          => 'text',
                'fields'        => [],
                'id'            => 'update_user_meta',
                'label'         => __( 'User Meta Key', 'gf-tools' ),
                'tooltip'       => __( '<strong>User Meta Key</strong>If the user submitting the form is logged in, the value will be updated on the user\'s meta.', 'gf-tools' ),
            ],
            // TODO: In a future version
            // [
            //     'section'       => 'advanced_settings',
            //     'position'      => 450,
            //     'type'          => 'checkbox',
            //     'fields'        => [ 'textarea' ],
            //     'id'            => 'hide_rich_text_buttons',
            //     'label'         => __( 'Hide Rich Text Editor Buttons', 'gf-tools' ),
            //     'tooltip'       => __( '<strong>Hide Buttons</strong>You might want to include a textarea field that allows you to inject HTML or other formatting while not allowing others to format. To do that, you need to use the Rich Text Editor and check this box to hide the buttons.', 'gf-tools' ),
            // ]
        ];
    } // End get_fields()


    /**
     * Add fields to the standard settings
     *
     * @param int $position
     * @param int $form_id
     * @return void
     */
    public function standard_settings( $position, $form_id ) {
        $this->add_settings( 'standard_settings', $position, $form_id );
    } // End standard_settings()


    /**
     * Add fields to the appearance settings
     *
     * @param int $position
     * @param int $form_id
     * @return void
     */
    public function appearance_settings( $position, $form_id ) {
        $this->add_settings( 'appearance_settings', $position, $form_id );
    } // End appearance_settings()


    /**
     * Add fields to the advanced settings
     *
     * @param int $position
     * @param int $form_id
     * @return void
     */
    public function advanced_settings( $position, $form_id ) {
        $this->add_settings( 'advanced_settings', $position, $form_id );
    } // End advanced_settings()


    /**
     * Add the settings
     *
     * @param string $section
     * @param int $position
     * @param int $form_id
     * @return void
     */
    public function add_settings( $section, $position, $form_id ) {
        foreach ( $this->get_fields() as $field ) {
            if ( $field[ 'section' ] !== $section ) {
                continue;
            }

            if ( $field[ 'position' ] == $position ) {
                if ( $field[ 'type' ] == 'text' ) {
                    if ( isset( $field[ 'merge_tag' ] ) && $field[ 'merge_tag' ] ) {
                        $merge_tag_class = ' merge-tag-support mt-position-right mt-prepopulate';
                    } else {
                        $merge_tag_class = '';
                    }
                    ?>
                    <li class="<?php echo esc_attr( $field[ 'id' ] ); ?>_setting field_setting gfadvtools">
                        <label for="field_<?php echo esc_attr( $field[ 'id' ] ); ?>" style="display:inline;"><?php echo esc_attr( $field[ 'label' ] ); ?><?php gform_tooltip( 'form_field_'.$field[ 'id' ] ) ?></label>
                        <input type="text" id="field_<?php echo esc_attr( $field[ 'id' ] ); ?>" class="field_<?php echo esc_attr( $field[ 'id' ] ); ?><?php echo esc_attr( $merge_tag_class ); ?>" autocomplete="off" onblur="SetFieldProperty('<?php echo esc_attr( $field[ 'id' ] ); ?>', this.value);"/>
                    </li>
                    <?php
                } elseif ( $field[ 'type' ] == 'checkbox' ) {
                    ?>
                    <li class="<?php echo esc_attr( $field[ 'id' ] ); ?>_setting field_setting gfadvtools">
                        <input type="checkbox" id="field_<?php echo esc_attr( $field[ 'id' ] ); ?>" onclick="SetFieldProperty( '<?php echo esc_attr( $field[ 'id' ]); ?>', this.checked );" onkeypress="SetFieldProperty( '<?php echo esc_attr( $field[ 'id' ] ); ?>', this.checked );"/>
                        <label for="field_<?php echo esc_attr( $field[ 'id' ] ); ?>" style="display:inline;"><?php echo esc_attr( $field[ 'label' ] ); ?><?php gform_tooltip( 'form_field_'.$field[ 'id' ] ) ?></label>
                    </li>
                    <?php
                }
            }
        }
    } // End settings()


    /**
     * Tooltips
     *
     * @param array $tooltips
     * @return array
     */
    public function tooltips( $tooltips ) {
        foreach ( $this->get_fields() as $field ) {
            $tooltips[ 'form_field_'.$field[ 'id' ] ] = $field[ 'tooltip' ];
        }
        return $tooltips;
    } // End tooltips()


    /**
     * Editor JS - Field Settings
     *
     * @return void
     */
    public function editor_script_field_settings() {
        // Field data
        $field_data = [];
        foreach ( $this->get_fields() as $field ) {
            $field_data[] = [
                'type'   => $field[ 'type' ],
                'id'     => $field[ 'id' ],
                'fields' => $field[ 'fields' ] ?? []
            ];
        }
        $inline_script = 'var gfatFields = ' . wp_json_encode( $field_data ) . ';';

        // Add the inline script
        wp_add_inline_script( 'gfadvtools_form_editor', $inline_script, 'before' );
    } // End editor_script_field_settings()


    /**
     * Add a custom field type
     *
     * @param array $field_groups
     * @return array
     */
    // public function field_groups_form_editor( $field_groups ) {
    //     foreach ( $field_groups as &$group ) {
    //         if ( $group['name'] == 'standard_fields' ) {
    //             $group['label'] = 'Testing';
    //         }
    //     }
    //     return $field_groups;
    // } // End field_groups_form_editor()
}