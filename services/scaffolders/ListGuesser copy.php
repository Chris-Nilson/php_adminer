<?php

namespace Services\Scaffolders;

use Abstracts\BaseBlueprint;
use Services\DateFormater;
use Services\Request;
use Services\Resource;
use Services\Translation;

class ListGuesser {
    private static $objects;
    private static $blueprint;
    private static $columns;
    private static $table_columns;

    public static function render(BaseBlueprint $blueprint, $objects) {

        if(empty($blueprint->get_columns())) return null;

        $primary_color = app('primaryColor');

        self::$blueprint = $blueprint;
        self::$columns = $blueprint->get_columns();
        self::$objects = $objects;
        self::$table_columns = array_keys(self::$columns);

        $route = Request::$request->php_admin_resource;

        $search_in = '';

        $class = get_class(self::$blueprint);

        // for search dropdown
        foreach (self::$table_columns as $key => $value) {

            $column = new ColumnAttribute(self::$columns[$value], $value);

            if($column->searchable == false) {
                continue;
            }

            $column_name_exploded = explode('.', $value);

            if($column->visible == false || empty($column->name)) {
                continue;
            }

            if(preg_match("/^field_divider_\d/", $value)) {
                continue;
            }

            // for search selection
            if(count($column_name_exploded) > 1 && $column->name == $value) {
                $h = ucfirst(  str_replace('_', ' ', $column_name_exploded[0])  );
            } else {
                $h = ucfirst(  str_replace('_', ' ', $column->name)  );
            }

            $search_in .= "<div class='item'>$h</div>";
        }

        // add create button if needed
        $create_element = "";
        $create_modal_form = "";
        $show_forms = "";
        if(self::$blueprint->createable()) {
            $create_element = "
                <span onclick=\"$('#create_form').modal({
                    transition: 'drop down',
                    closable:false,
                }).modal('show')\"
                
                class='ui button mini blue ' href='/{$route}/create'>
                    <i class=' ui icon plus'></i>
                    ". Translation::translate("create") ."
                </span>
            ";

            $create_modal_form = "<div class='ui large modal' id='create_form'>";
                $create_modal_form .= "<i class='ui close icon'></i>";
                $create_modal_form .= "<div class='header'>";
                    $create_modal_form .= "<i class='ui folder open outline icon'></i> | " . $blueprint->get_name_singular() ." | ". Translation::translate('create');
                $create_modal_form .= "</div>";
    
                $create_modal_form .= "<div class='content scrolling'>";
                    $create_modal_form .= Resource::call($class, [], 'create');
                $create_modal_form .= "</div>";
            $create_modal_form .= "</div>";
        }


        // delete create button if needed
        $delete_element = "";
        $delete_modal_confirm = "";
        if(self::$blueprint->deleteable()) {
            $delete_element = "
                <span onclick=\"
                    $('#confirm_delete').modal({
                        transition: 'fly',
                        closable: false,
                    }).modal('show')\"
                class='ui button mini orange' >
                <i class=' ui icon trash'></i>
                    ". Translation::translate("delete") ."
                </span>

                <button id='multiple_delete_button' name='php_admin_delete_multiple' style='display: none'>
                <i class=' ui icon trash'></i>
                    ". Translation::translate("delete") ."
                </buttom>
            ";

            if($blueprint->deleteable()) {
                
                $delete_modal_confirm = "<div class='ui modal mini' id='confirm_delete'>";
                    $delete_modal_confirm .= "<div class='header'>";
                        $delete_modal_confirm .= Translation::translate('are_you_sure_to_delete');
                    $delete_modal_confirm .= "</div>";
        
                    $delete_modal_confirm .= "<div class='actions'>";
                        $delete_modal_confirm .= "<button onclick=\"$('#multiple_delete_button').trigger('click')\" class='ui button small orange'>". Translation::translate('delete') ."</button>";
                        $delete_modal_confirm .= "<button class='ui button small olive deny'>". Translation::translate('cancel') ."</button>";
                    $delete_modal_confirm .= "</div>";
                $delete_modal_confirm .= "</div>";

            }
            
        }


        // export create button if needed
        $export_element = "";
        if(self::$blueprint->exportable()) {
            $export_element = "
                <button name='php_admin_export' value='' class='ui button mini yellow' >
                    <i class=' ui icon save'></i>
                    ". Translation::translate("export") ."
                </button>
            ";
        }


        $update_modal_forms = "";
        $elements = "<div class=''>";
            // we need form for checkboxes
            $elements .= "<form class='' id='list_form' action='' method='POST'>";

            // search and delete and export
            $elements .= "
                <div class='ui segment'>
                    <div class='ui two column grid stackable'>
                        <div class='column'>
                            <div class='ui left labeled input mini'>
                            <div class='ui dropdown search selection label'>
                                <input type='hidden' placeholder='Search' id='search_in'>
                                <div class='text'>". Translation::translate('search in') ."</div>
                                <div class='menu'>
                                    {$search_in}
                                </div>
                            </div>
                                <input type='text' role='text' placeholder='". Translation::translate('search in') ."' id='search_value'>
                                <!--button class='ui button small blue deny'>". Translation::translate('search in all') ."</button-->
                            </div>
                        </div>
                        <div class='column uk-text-right'>
                            {$create_element}
                            {$delete_element}
                            {$export_element}
                        </div>
                    </div>

                </div>
            ";

            $elements .="<div class='uk-overflow-auto'>";

                $elements .= "<table id='table_of_resource' class='ui unstackable single line table compact'>";
                // $elements .= "<table id='table_of_resource' class='ui striped celled table ". app('primaryColor') ." compact'>";

                    $elements .= "<thead>";
                        $elements .= "<tr>";

                        // check all
                        if($blueprint->exportable()) {
                            $elements .= "<th class='no-sort uk-text-center collapsing'><input type='checkbox' class='uk-checkbox' id='check_all_objcts'></th>";
                        }

                        // add table heads
                        foreach(self::$table_columns as $table_head) {
                            $column = new ColumnAttribute(self::$columns[$table_head], $table_head);

                            if($column->visible == false || $column->type == 'blueprint') {
                                continue;
                            }

                            if(preg_match("/^field_divider_\d/", $table_head)) {
                                continue;
                            }

                            // TODO check if the column is an array or an object

                            $sub_element = explode('.', $table_head);

                            if(count($sub_element) > 1 && $column->name == $table_head) {
                                $elements .= "<th class='collapsing'>".ucfirst(  str_replace('_', ' ', $sub_element[0])  )."</th>";
                            } else {
                                $elements .= "<th class='collapsing'>".ucfirst(  str_replace('_', ' ', $column->name)  )."</th>";
                            }

                        }

                            // actions
                            $elements .= "<th class='collapsing center aligned no-sort'>Actions</th>";

                        $elements .= "</tr>";
                    $elements .= "</thead>";

                    // table body now
                    $elements .= "<tbody>";
                        // for each object in objects fetched
                        foreach(self::$objects as $obj) {
                            $uid_field = $blueprint->uid_field();
                            $uid = $obj->$uid_field;
                            Request::$request->uid = $uid;
                            $row_data_id = substr(md5($uid), 0, 10);

                            if($blueprint->editable($obj)) {
                                $update_modal_forms .= "<div class='ui large modal' id='$row_data_id'>";
                                    $update_modal_forms .= "<i class='ui close icon'></i>";
                                    $update_modal_forms .= "<div class='header'>";
                                        $update_modal_forms .= "<i class='ui folder open outline icon'></i> | " . $blueprint->get_name_singular() ." | ". Translation::translate('update');
                                    $update_modal_forms .= "</div>";
    
                                    $update_modal_forms .= "<div class='content scrolling'>";
                                        $update_modal_forms .= Resource::call($class, (Array)$obj, 'edit');
                                    $update_modal_forms .= "</div>";
                                $update_modal_forms .= "</div>";
                            }
                            
                            if($blueprint->deleteable($obj)) {

                                $delete_modal_confirm .= "<div class='ui modal mini' id='confirm_delete_$row_data_id'>";
                                    $delete_modal_confirm .= "<div class='header'>";
                                        $delete_modal_confirm .= Translation::translate('are_you_sure_to_delete');
                                    $delete_modal_confirm .= "</div>";

                                    $delete_modal_confirm .= "<form method='POST' class='actions' action='/{$route}'>";
                                        $delete_modal_confirm .= "<input name='php_admin_action' type='hidden' value='delete'>";
                                        $delete_modal_confirm .= "<input name='php_admin_uid' type='hidden' value='$uid'>";
                                        $delete_modal_confirm .= "<button onclick=\"$('#multiple_delete_button').trigger('click')\" class='ui button small orange'>". Translation::translate('delete') ."</button>";
                                        $delete_modal_confirm .= "<button type='button' class='ui button small olive deny'>". Translation::translate('cancel') ."</button>";
                                    $delete_modal_confirm .= "</form>";
                                $delete_modal_confirm .= "</div>";
                            }

                            $elements .= "<tr style='cursor: pointer;'>";


                            // checkboxes
                            if($blueprint->exportable()) {
                                $elements .= "<td class='collapsing uk-text-center'><input type='checkbox' name='selected_id[]' value='{$uid}' class=' uk-checkbox selected_ids'></td>";
                            }

                            
                            // for each column in columns
                            // $show_forms .= "<form action='/{$route}' method='GET' id='show_$row_data_id'>
                            //     <input type='hidden' name='php_admin_action' value='show'>
                            //     <input type='hidden' name='php_admin_uid' value='$uid'>
                            // </form>";
                            foreach(self::$table_columns as $column_name) {
                                // $show_link = "onclick='$(\"#show_$row_data_id\").submit()'";
                                $show_link = "onclick='document.location = \"/{$route}/show/{$uid}\";'";

                                $cell_values = "";
                                $cell_value = "";

                                $column = new ColumnAttribute(self::$columns[$column_name], $column_name);

                                if($column->visible == false || $column->type == 'blueprint') {
                                    continue;
                                }

                                // escape dividers
                                if(preg_match("/^field_divider_\d/", $column_name)) {
                                    continue;
                                }


                                // tooltip
                                $tooltip = $column->tooltip;
                                $tooltip = empty($tooltip) ? '' :  'data-tooltip="'.$tooltip.'"';

                                // css classes
                                $labeled = $column->labeled;
                                $labeled = "class='{$labeled}'";

                                $replacements = $column->replacements;

                                // categoty.name becomes category, name
                                $sub_element = explode('.', $column_name);

                                //column is an array or object
                                if(count($sub_element) > 1){
                                    // todo
                                    $current_level = $obj;
                                    foreach ($sub_element as $level) {
                                        if(isset($current_level->$level)) {
                                            $current_level = $current_level->$level;
                                        }
                                    }
                                    // final value
                                    // TODO handle object array
                                    
                                    if(!is_array($current_level) && !is_object(($current_level))) {

                                        if($callback_return = self::callback($column_name, $current_level)) {
                                            $cell_value = $callback_return;
        
                                            $elements .= self::build_cell($column, $cell_value, $show_link, $tooltip, $labeled, true );
                                            continue;
                                        }

                                        $current_level = $replacements[$current_level] ?? $current_level;

                                        $elements .= self::build_cell($column, $current_level, $show_link, $tooltip, $labeled);

                                        continue;
                                    }
                                    else {
                                        $elements .= '<td></td>';
                                        continue;
                                    }

                                    // TODO handle iterable data #isArray
                                    // else {

                                    // }
                                }

                                // column is an object and no sub_property set or set explicitly
                                if (is_object($obj->$column_name)) {
                                    $sub_property = $column->relation;

                                    if (isset($obj->$column_name->$sub_property)) {
                                        $cell_value = $obj->$column_name->$sub_property;
                                    } else {
                                        $tooltip = "data-tooltip='id missing/sub_property not set'";
                                        $labeled = "class='ui label red fluid uk-text-center'";
                                        $cell_value = 'error';
                                    };

                                    if($callback_return = self::callback($column_name, $cell_value)) {
                                        $cell_value = $callback_return;
    
                                        $elements .= self::build_cell($column, $cell_value, $show_link, $tooltip, $labeled, true );
                                        continue;
                                    }
                                    

                                    $cell_value = $replacements[$cell_value] ?? $cell_value;

                                    $elements .= self::build_cell($column, $cell_value, $show_link, $tooltip, $labeled);

                                    continue;
                                }

                                // TODO implement callback
                                // column is an array and no sub_property set or set explicitly
                                if (is_array($obj->$column_name)) {
                                    if (empty($obj->$column_name)) {
                                        $cell_values .= "";
                                    } else {
                                        foreach ($obj->$column_name as $key => $value) {
                                            //TODO

                                            $val = ((Array)$value)[$sub_property];
                                            $sub_property = $column->relation;
                                            if (isset($value->$sub_property)) {

                                                $cell_values .= "<span {$labeled}>". self::build_cell($column, $val, $show_link, $column->tooltip, $column->labeled) ."</span>";
                                            } else {
                                                $labeled = "class='ui label red uk-text-center'";
                                                $tooltip = "data-tooltip='id missing/sub_property not set'";
                                                $cell_values .= "<span {$labeled}>error</span>";
                                            }
                                        }
                                    }

                                    // TODO check for replacements
                                    $elements .=
                                    "<td {$show_link} class='collapsing'>
                                        <span {$tooltip} >".
                                            $cell_values
                                        ."</span>
                                    </td>";

                                    $cell_values = "";

                                    continue;
                                }

                                $cell_value = $obj->$column_name ?? '';

                                if($callback_return = self::callback($column_name, $cell_value)) {
                                    $cell_value = $callback_return;

                                    $elements .= self::build_cell($column, $cell_value, $show_link, $tooltip, $labeled, true );
                                    continue;
                                }

                            
                                if(isset($replacements[$cell_value])) {
                                    $cell_value = $replacements[$cell_value];
                                } else {
                                    if(isset($column->values)) {
                                        $cell_value = $column->values[$obj->$column_name];
                                    } else {
                                        $cell_value = $obj->$column_name ?? '';
                                    }
                                }

                                $elements .= self::build_cell($column, $cell_value, $show_link, $tooltip, $labeled);

                            }

                            // actions links
                            $elements .= "<td class='collapsing center aligned'>";
                                $elements .= "<a href='/{$route}/show/{$uid}' class='uk-link-text uk-link-reset'><i class='ui icon eye gree'></i></a>";

                                if($blueprint->editable($obj))
                                    $elements .= "<span onclick=\"
                                    $('#$row_data_id').modal({
                                        transition: 'drop',
                                        closable:false,
                                    }).modal('show')\"
                                    href='/{$route}/edit/{$uid}' class='uk-link-text uk-link-reset'><i class='ui icon edit blue'></i></span>";

                                if($blueprint->deleteable($obj))
                                    $elements .= "<span onclick=\"
                                        $('#confirm_delete_$row_data_id').modal({
                                            transition: 'fly',
                                            closable: false,
                                        }).modal('show')\"
                                    class='uk-link-text uk-link-reset'><i class='ui icon trash orange'></i></span>";
                            $elements .= "</td>";

                            $elements .= "</tr>";

                            continue;
                        }


                    $elements .= "</tbody>";

                $elements .= "</table>";

            $elements .= "</div>";

            if($blueprint->local_paginate()) {
                $elements .= <<<EOT
                    <div class="ui pagination mini menu uk-margin-top">
                        <a class="item" id="paginator_prev"> <i class="ui chevron left icon"></i> </a>
                        <div class="item ui mini transparent input" style="padding: 0px !important; margin: 0px !important;">
                            <input type="number" id="current_page_field" value="1" style="text-align: center; font-size: 1.6em; width: 60px;">
                        </div>
                        <span href="" class="item">/</span>
                        <div class="item ui mini transparent input" style="padding: 0px !important; margin: 0px !important;">
                            <input type="text" disabled id="last_page_field" value="" style="text-align: center; font-size: 1.6em; width: 60px;">
                        </div>
                        <select class="ui dropdown compact" id="per_page_field" style="text-align: center; font-size: 1.6em; border: none !important;">
                            <option value="5">05</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="30" selected>30</option>
                            <option value="50">50</option>
                            <option value="80">80</option>
                            <option value="100">100</option>
                        </select>
                        <a class="item" id="paginator_next"> <i class="ui chevron right icon"></i> </a>
                    </div>
                EOT;
            }

            $elements .= "</form>";

            // pagination
            if($blueprint->can_paginate() && $blueprint->paginate('last_page') != 1) {
                if(isset(Request::$request->page))
                    $page = Request::$request->page <= 1 ? 1 : Request::$request->page;
                else
                    $page = 1;

                $prev = $page <= 1 ? 1 : $page-1;

                $last_page = $blueprint->paginate('last_page');
                $page_name = $blueprint->paginate('page_name') ?? 'page';
                $count = $blueprint->paginate('count');

                if(!empty($objects)) {
                    $next = $page+1;
                } else {
                    $next = $page;
                }

                $elements .= "<div class='ui segment right floate'>";


                    $elements .= "<div class='ui two column very relaxed grid'>";

                        $elements .= "<div class='column'>";
                        if($count) {
                            $elements .= "
                                <span class='ui label $primary_color basic'>
                                    Total: $count
                                </span>";
                        }
                        $elements .= "</div>";


                        $elements .= "<div class='column right aligned'>";

                            if($page <= 1) {
                                $elements .= "<a href='?page=$prev' class='ui button icon $primary_color circular basic mini disabled'><i class='ui icon arrow left'></i></a>";
                            } else {
                                $elements .= "<a href='?page=$prev' class='ui button icon $primary_color circular basic mini'><i class='ui icon arrow left'></i></a>";
                            }
                            $elements .= "
                            <span class='ui tiny form'>
                                <form class='inline field uk-inline' method='get' action='".url()."'>
                                    <label for='paginate_to'>Page: </label>
                                    <input type='number' min='1' max='$last_page' value='$page' id='paginate_to' name='{$page_name}' style='width: 80px;'>
                                    <label for=''> / {$last_page} </label>
                                    
                                </form> 
                            </span>";

                            if($last_page) {
                                if($page < $last_page) {
                                    $elements .= "<a href='".url()."?{$page_name}={$next}' class='ui button icon $primary_color circular basic mini'><i class='ui icon arrow right'></i></a>";
                                } else {
                                    $elements .= "<a href='".url()."?{$page_name}={$next}' class='ui button icon $primary_color circular basic mini disabled'><i class='ui icon arrow right'></i></a>";
                                } 
                            } else {

                                if(!empty($objects)) {
                                    $elements .= "<a href='".url()."?{$page_name}={$next}' class='ui button icon $primary_color circular basic mini'><i class='ui icon arrow right'></i></a>";
                                }
                            
                            }
                        $elements .= "</div>";

                        
                    $elements .= "</div>";
                    $elements .= "<div class='ui vertical divider'></div>";
                $elements .= "</div>";
            }



        $elements .= "</div>";

        $elements .= $update_modal_forms;
        $elements .= $create_modal_form;
        $elements .= $delete_modal_confirm;
        $elements .= $show_forms;
        return $elements;
    }

    private static function callback($column, $value) {
        if(isset(self::$blueprint->callbacks()[$column])) {
            $callback = self::$blueprint->callbacks()[$column];

            if(method_exists(self::$blueprint, $callback)) {
                
                $build_result = self::$blueprint->$callback($value);
                return $build_result;
            }
        }

        return null;
    }

    private static function build_cell(ColumnAttribute $column, $cell_value, $cell_link = null, $tooltip = null, $labeled = null, $callbacked = false) {

        $elements = "";

        $re1 = '%(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?%iu';
        // $re1 = '%^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu';
        $re2 = '/(\w+((\.|-|_)?(\w)+)*@(\w+|-|_|\.)+\.\w{2,5})/i';

        if($callbacked) {
            $cell_link = preg_match($re1, $cell_value) ? '' : $cell_link;
            $elements .=
            "<td {$cell_link} class=''>
                <span {$tooltip} {$labeled}>".
                    $cell_value
                ."</span>
            </td>";

            return $elements;
        }

        if(preg_match('/\w*\.(jpeg|png|bmp|gif|jpg|ico|tiff)/', $cell_value)) {
            $form = '';       
            if(isset($column->image)) {
                $form = $column->image;
            }

            $form = in_array($form, ['rounded', 'circular', 'avatar']) ? $form : 'rounded';

            $style = "style='height: 60px; width: auto' alt=''";
            
            if($form == 'avatar') {
                $style = "style='height: 60px; width: 60px' alt=''";
            }

            $elements .=
            "<td class='' uk-lightbox>
                <a href='$cell_value'>
                <img {$tooltip} src='$cell_value' class='ui $form image tiny' {$style}>
                </a>
            </td>";
            return $elements;
        }

        $cell_value = DateFormater::format(app('dateFormat'), $cell_value);


        // link

        if(preg_match($re1, $cell_value, $matches)) {
            $cell_value = "<a href='{$matches[0]}' target='_blank' data-tooltip='$matches[0]'>". Translation::translate('internet_link') ." <i class='external alternate icon'></i></a>";
            $cell_link = '';

            $elements .=
            "<td {$cell_link} class=''>
                <span {$tooltip} {$labeled}>".
                    $cell_value
                ."</span>
            </td>";

            return $elements;
        } else if(preg_match($re2, $cell_value, $matches)) { // email
            $cell_value = "<a href='mailto:{$matches[0]}' data-tooltip='$matches[0]' target='_blank'><i class='ui icon mail'></i>". Translation::translate('email_link') ."</a>";
            $cell_link = '';

            $elements .=
            "<td {$cell_link} class=''>
                <span {$tooltip} {$labeled}>".
                    $cell_value
                ."</span>
            </td>";

            return $elements;
        }

        if(preg_match("/(\d\d:\d\d):\d\d/", $cell_value, $matches)) {
            $cell_value = $matches[1];
        }


        $ellapsis = (strlen($cell_value) > 40) ? '...' : '';
        $elements .=
        "<td {$cell_link} class=''>
            <span {$tooltip} {$labeled}>".
                substr($cell_value, 0, 40).$ellapsis 
            ."</span>
        </td>";

        return $elements;
    }
}

?>
