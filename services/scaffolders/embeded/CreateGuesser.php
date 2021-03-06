<?php

namespace Services\Scaffolders\Embeded;

use Abstracts\BaseBlueprint;
use Services\API;
use Services\Auth;
use Lib\form\Checkbox;
use Lib\form\Dropdown;
use Services\Request;
use Services\Scaffolders\ColumnAttribute;
use Services\Scaffolders\CustomBlueprint;
use Services\Translation;

class CreateGuesser {

    public static function render(BaseBlueprint $blueprint, string $parent_variable = '') {

        $primary_color = app('primaryColor');

        $parent_variable = $parent_variable."[index][";

        $keys = array_keys($blueprint->get_columns());

        $resource = Request::$request->php_admin_resource;

        $elements = "<div class='embeded_row ui segment'>";

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

                    if(!$column->createable) {
                        continue;
                    }


                    $sub_element = explode('.', $value);

                    // has url to fetch data from
                    if(count($sub_element) > 1 or $column->endpoint) {

                        $required_indicator = '';
                        if($column->required) {
                            $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") (". $column->detail .")</small>";
                        }


                        // if url to fecth data we will fill the dropdown with not set
                        if(!$column->endpoint) {
                            $elements .= "<div class='column' id='{$column->id}_container'>";
                                $elements .= "<div class='ui form'>";
                                    $elements .= "<div class='field {$column->disabled}'>";
                            
                                        $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                        $elements .= "<div class='ui input small'>";
                                        $elements .= "<input class='{$column->class}' id='{$column->id}' maxlength='{$column->length}' {$column->required} autocomplete='new-password' value='' 
                                        type='".$column->type."' id='".$value."' 
                                        name='$parent_variable".$column->variable."]' 
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
                            $checkbox = new Checkbox($parent_variable.$column->variable.']');
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

                            $dropdown = new Dropdown($parent_variable.$column->variable.']', null, $column->name, $column->required, $column->id, $column->class);
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
                                    $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") (". $column->detail .")</small>";
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

                        $dropdown = new Dropdown($parent_variable.$column->variable.']', null, $column->name, $column->required, $column->id, $column->class);
                        foreach($column->values as $val => $label) {
                            $dropdown->define($label, $val, $column->option_image ?? null);
                        }

                        $elements .= "<div class='column' id='{$column->id}_container'>";
                            $elements .= "<div class='ui form'>";
                                $elements .= "<div class='field {$column->disabled}'>";


                                $required_indicator = '';
                                if($column->required) {
                                    $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") (". $column->detail .")</small>";
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
                        $checkbox = new Checkbox($parent_variable.$column->variable.']');
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
                                        $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") (". $column->detail .")</small>";
                                    }

                                    $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                    $elements .= $checkbox->render();

                                $elements .= "</div>";
                            $elements .= "</div>";
                        $elements .= "</div>";
                        continue;
                    }

                    // is text
                    if($column->type == 'longtext') {

                        $elements .= "<div class='column' id='{$column->id}_container'>";
                            $elements .= "<div class='ui form'>";
                                $elements .= "<div class='field {$column->disabled}'>";
    
                                    $required_indicator = '';
                                    if($column->required) {
                                        $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") (". $column->detail .")</small>";
                                    }
                                    $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                    $elements .= "<div class='ui input small'>";
                                        $elements .= "<textarea class='{$column->class}' id='{$column->id}' maxlength='{$column->length}' style='resize: vertical; height: 100px' type='".$column->type."' id='".$value."' name='$parent_variable".$column->variable."]' placeholder='".str_replace("'", " ", $column->name)."'></textarea>";
                                    $elements .= "</div>";

                                $elements .= "</div>";
                            $elements .= "</div>";
                        $elements .= "</div>";
                        continue;

                    }

                    // other types
                    $field_value = null;
                    if($column->type == 'number') {
                        $field_value = 0;
                    }
                    $elements .= "<div class='column' id='{$column->id}_container'>";
                        $elements .= "<div class='ui form'>";
                            $elements .= "<div class='field {$column->disabled}'>";

                                $required_indicator = '';
                                if($column->required) {
                                    $required_indicator = "<small class='uk-text-danger'>  (".Translation::translate('field required').") (". $column->detail .")</small>";
                                }
                                $elements .= "<label for='".$value."'>".$column->name.$required_indicator."</label>";
                                $elements .= "<div class='ui input small'>";
                                    $elements .= "<input class='{$column->class}' id='{$column->id}' maxlength='{$column->length}' {$column->required} value='{$field_value}' autocomplete='new-password' type='".$column->type."' id='".$value."' name='$parent_variable".$column->variable."]' placeholder='".str_replace("'", " ", $column->name)."'>";
                                $elements .= "</div>";

                            $elements .= "</div>";
                        $elements .= "</div>";
                    $elements .= "</div>";

                }
                $elements .= "<button type='button' class ='ui top right floating label circular icon mini red button embeded_row_remover uk-margin-small-right'><i class='ui remove icon'></i></button>";


            $elements .= "</div>";



        $elements .= "</div>";

        return $elements;

    }
}

?>
