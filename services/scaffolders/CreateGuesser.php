<?php

namespace Services\Scaffolders;

use Abstracts\BaseBlueprint;
use Services\API;
use Services\Auth;
use Lib\form\Checkbox;
use Lib\form\Dropdown;
use Services\Request;
use Services\Session;
use Services\Translation;

class CreateGuesser {

    public static function render(BaseBlueprint $blueprint) {

        $primary_color = app('primaryColor');

        $keys = array_keys($blueprint->get_columns());

        $resource = Request::$request->php_admin_resource;
        $php_admin_form_action = Request::$request->php_admin_form_action ?? $resource;

        $embeded_blueprints = "";

        $blueprint->createPreRenderConfig();

        $elements = "<form autocomplete='new-password' action='/{$php_admin_form_action}' method='POST' enctype='multipart/form-data'>";

            $elements .= "<input name='php_admin_action' type='hidden' value='save'>";

            $elements .= "<div class='ui three column stackable grid'>";

                foreach ($keys as $key => $value) {

                    if(in_array($value, $blueprint->get_guarded())) {
                        continue;
                    }

                    if( preg_match("/^field_divider_\d/", $value ) ) {
                        $value = $blueprint->get_columns()[$value];
                        $icon = isset($value['icon']) ? "<i class='{$value['icon']} icon'></i>" : '';
                        $legend = isset($value['legend']) ? $value['legend'] : '';
                        $elements .= "
                            <div class='row one column grid'>
                            <div class='column'>
                                <h6 class='ui horizontal divider tiny header'>
                                    $icon
                                    $legend
                                </h6>
                            </div>
                            </div>";
                        continue;
                    }

                    // $column = (Object)$blueprint->get_columns()[$value];

                    $column = new ColumnAttribute((Object)$blueprint->get_columns()[$value], $value);

                    $column->name = str_replace('_',' ' ,ucfirst($column->name));

                    // Type Blueprint or embeded
                    if($column->type == 'blueprint' && is_array($column->columns)) {
                        $embeded_form = "";
                        $temp_blueprint = new CustomBlueprint();
                        $temp_blueprint->set_columns($column->columns);
                        $embeded_form .= $temp_blueprint->embeded_create($column->variable);
                        
                        $embeded_blueprints .= "<h4 class='embeded_row_add_btn'>{$column->name} <button type='button' class='ui button mini blue floated right'>Add</button></h4>";
                        $embeded_blueprints .= "<div class='embeded_row_sample' style='display:none'>".$embeded_form."</div>";
                        $embeded_blueprints .= "<div class='embeded_main_rows_container'></div>";

                        continue;
                    }

                    if(!$column->createable) {
                        continue;
                    }


                    $sub_element = explode('.', $value);

                    // has url to fetch data from
                    if(count($sub_element) > 1 or $column->endpoint) {

                        $required_indicator = '';
                        if($column->required) {
                            $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") ". $column->detail ."</small>";
                        }


                        // if url to fecth data we will fill the dropdown with not set

                        $old_value = Session::get('create_old_data')[$column->variable] ?? '';

                        if(!$column->endpoint) {
                            $elements .= "<div class='column' id='{$column->id}_container'>";
                                $elements .= "<div class='ui form'>";
                                    $elements .= "<div class='field {$column->disabled}'>";
                            
                                        $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                        $elements .= "<div class='ui input small'>";
                                        $column_id = $column->id ?? "id='{$column->id}'";
                                        $elements .= "<input $column->accept class='{$column->class}' maxlength='{$column->length}' {$column->required} autocomplete='new-password' 
                                        type='".$column->type."' 
                                        name='".$column->variable."' 
                                        value = '". $old_value ."'
                                        placeholder=". '"' . $column->name . '"' .">";
                                        $elements .= "</div>";
                                    $elements .= "</div>";
                                $elements .= "</div>";
                            $elements .= "</div>";
                            continue;
                        }

                        if(count($sub_element) == 1) {
                            array_push($sub_element, $column->relation);
                        }

                        $api = new API();
                        $api->header("Authorization", app('authType').' '.Auth::token());
                        $api->callWith(app('baseUrl').$column->endpoint, $column->fetch_method);
                        $sub_response = $api->response();


                        // array_shift($sub_element);
                        $last_child = $sub_element[count($sub_element)-1];
                        array_pop($sub_element);

                        $relation = $column->relation;
                        $option_image = $column->option_image;
                        
                        array_shift($sub_element);

                        $cell_value = "";
                        if($column->type == 'array') {
                            $checkbox = new Checkbox($column->variable);
                            foreach ($sub_response as $single_object) {
                                $current_level = $single_object;
                                foreach ($sub_element as $level) {
                                    if(isset($current_level->$level)) {
                                        $current_level = $current_level->$level;
                                    }
                                }

                                $checkbox->define($current_level->$last_child, $current_level->$relation);
                            }

                            $cell_value = $checkbox->render();
                        } else {

                            $old_value = Session::get('create_old_data')[$column->variable] ?? null;
                            $dropdown = new Dropdown($column->variable, $old_value, $column->name, $column->required, $column->id, $column->class);
                            foreach ($sub_response as $single_object) {
                                $current_level = $single_object;
                                foreach ($sub_element as $level) {
                                    if(isset($current_level->$level)) {
                                        $current_level = $current_level->$level;
                                    }
                                }
    
                                $dropdown->define($current_level->$last_child, $current_level->$relation, $current_level->$option_image ?? null);
                            }
                            $cell_value = $dropdown->render();
                        }

                        
                        $elements .= "<div class='column' id='{$column->id}_container'>";
                            $elements .= "<div class='ui form'>";
                                $elements .= "<div class='field {$column->disabled}'>";


                                $required_indicator = '';
                                if($column->required) {
                                    $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") ". $column->detail ."</small>";
                                }

                                $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                $elements .= "<div class='ui input'>";
                                    $elements.= $cell_value;
                                    // $elements.= $dropdown->render();
                                $elements .= "</div>";

                                $elements .= "</div>";
                            $elements .= "</div>";
                        $elements .= "</div>";
                        continue;
                    }

                    // has values defined and object (dropdown)
                    if( ($column->type == 'object' and !empty($column->values)) and is_array($column->values)) {

                        $old_value = Session::get('create_old_data')[$column->variable] ?? null;
                        $dropdown = new Dropdown($column->variable, $old_value, $column->name, $column->required, $column->id, $column->class);
                        foreach($column->values as $val => $label) {
                            $dropdown->define($label, $val, $column->option_image ?? null);
                        }

                        $elements .= "<div class='column' id='{$column->id}_container'>";
                            $elements .= "<div class='ui form'>";
                                $elements .= "<div class='field {$column->disabled}'>";


                                $required_indicator = '';
                                if($column->required) {
                                    $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") ". $column->detail ."</small>";
                                }

                                $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                $elements .= "<div class='ui input'>";
                                    $elements.= $dropdown->render();
                                $elements .= "</div>";

                                $elements .= "</div>";
                            $elements .= "</div>";
                        $elements .= "</div>";
                        continue;
                    }

                    // has values defined and array (checkbox)
                    if (($column->type == 'array' and !empty($column->values)) and is_array($column->values)) {
                        $checkbox = new Checkbox($column->variable.'[]');
                        foreach ($column->values as $val => $label) {
                            $checkbox->define($label, $val);
                        }

                        $column->name = $sub_element[0];
                        $column->name = str_replace('_',' ' ,ucfirst($column->name));

                        $elements .= "<div class='column' id='{$column->id}_container'>";
                            $elements .= "<div class='ui form'>";
                                $elements .= "<div class='field {$column->disabled}'>";

                                    $required_indicator = '';
                                    if($column->required) {
                                        $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") ". $column->detail ."</small>";
                                    }

                                    $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                    $elements .= $checkbox->render();

                                $elements .= "</div>";
                            $elements .= "</div>";
                        $elements .= "</div>";
                        continue;
                    }

                    // is text
                    $old_value = Session::get('create_old_data')[$column->variable] ?? '';
                    if($column->type == 'longtext') {

                        $elements .= "<div class='column' id='{$column->id}_container'>";
                            $elements .= "<div class='ui form'>";
                                $elements .= "<div class='field {$column->disabled}'>";
    
                                    $required_indicator = '';
                                    if($column->required) {
                                        $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") ". $column->detail ."</small>";
                                    }
                                    $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                    $elements .= "<div class='ui input small'>";
                                        $column_id = $column->id ?? "id='{$column->id}'";
                                        $elements .= "<textarea $column->required class='{$column->class}' maxlength='{$column->length}' style='resize: vertical; height: 100px' type='".$column->type."' name='".$column->variable."' placeholder='".str_replace("'", " ", $column->name)."'>$old_value</textarea>";
                                    $elements .= "</div>";

                                $elements .= "</div>";
                            $elements .= "</div>";
                        $elements .= "</div>";
                        continue;

                    }


		// checkbox
		if($column->type == 'checkbox') {

                        $elements .= "<div class='column' id='{$column->id}_container'>";
                            $elements .= "<div class='ui form'>";
                                $elements .= "<div class='field {$column->disabled}'>";
    
                                    $required_indicator = '';
                                    if($column->required) {
                                        $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") ". $column->detail ."</small>";
                                    }
                                    $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                    $elements .= "<div class='ui slider checkbox'>";
                                        $column_id = $column->id ?? "id='{$column->id}'";
                                        $elements .= "<input type='checkbox' $column->required class='{$column->class}'>";
					$elements .= "<label>{$column->label}</label>";
                                    $elements .= "</div>";

                                $elements .= "</div>";
                            $elements .= "</div>";
                        $elements .= "</div>";
                        continue;

                    }

                    // other types
                    $old_value = Session::get('create_old_data')[$column->variable] ?? '';
                    
                    if($column->type == 'number') {
                        $old_value = Session::get('create_old_data')[$column->variable] ?? 0;
                    }

                    // is file
                    if($column->type == 'file') {
                        $old_value = null;
                    }
                    
                    $elements .= "<div class='column' id='{$column->id}_container'>";
                        $elements .= "<div class='ui form'>";
                            $elements .= "<div class='field {$column->disabled}'>";

                                $required_indicator = '';
                                if($column->required) {
                                    $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") ". $column->detail ."</small>";
                                }
                                $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                $elements .= "<div class='ui input small'>";
                                    $column_id = $column->id ?? "id='{$column->id}'";
                                    $elements .= "<input $column->accept class='{$column->class}' maxlength='{$column->length}' {$column->required} 
                                    value='{$old_value}' 
                                    autocomplete='new-password' type='".$column->type."' name='".$column->variable."' placeholder='".str_replace("'", " ", $column->name)."'>";
                                $elements .= "</div>";

                            $elements .= "</div>";
                        $elements .= "</div>";
                    $elements .= "</div>";
                }


            $elements .= "</div>";

            $elements .= $embeded_blueprints;

            $elements .= "<div class='uk-margin uk-text-right'>";
                $elements .= "<button data-url=\"".app('baseUrl').$blueprint->endpoints()->create."\" data-method='{$blueprint->endpoints_methods()->create}' class='ui button green small resource_create_submit_button' type='submit'><i class='ui icon check'></i>". Translation::translate("save") ."</button>";
            $elements .= "</div>";



        $elements .= "</form>";

        return $elements;

    }
}

?>
