<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Middleware\Pjax;
use Encore\Admin\Form\Field;
use Illuminate\Http\Request;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Services\Plugin\PluginInstaller;
use Symfony\Component\HttpFoundation\Response;
use Exceedone\Exment\Services\DataImportExport;
use Exceedone\Exment\Form\Field as ExmentField;

class CustomValueController extends AdminControllerTableBase
{
    use ModelForm, AuthorityForm, DocumentForm, CustomValueGrid, CustomValueForm;
    protected $plugins = [];
    //use ModelForm, AuthorityForm;

    /**
     * CustomValueController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);

        $this->setPageInfo($this->custom_table->table_view_name, $this->custom_table->table_view_name, $this->custom_table->description);

        if (!is_null($this->custom_table)) {
            //Get all plugin satisfied
            $this->plugins = PluginInstaller::getPluginByTable($this->custom_table->table_name);
        }
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index(Request $request, Content $content)
    {
        $this->setFormViewInfo($request);
        $this->AdminContent($content);

        // if table setting is "one_record_flg" (can save only one record)
        if (boolval($this->custom_table->one_record_flg)) {
            // get record list
            $record = $this->getModelNameDV()::first();
            // has record, execute
            if (isset($record)) {
                $id = $record->id;
                $form = $this->form($id)->edit($id);
                $form->setAction(admin_base_path("data/{$this->custom_table->table_name}/$id"));
                $content->body($form);
            }
            // no record
            else {
                $form = $this->form(null);
                $form->setAction(admin_base_path("data/{$this->custom_table->table_name}"));
                $content->body($form);
            }
        } else {
            $content->body($this->grid());
        }
        return $content;
    }

    /**
     * Show interface.
     *
     * @param $id
     * @return Content
     */
    public function show(Request $request, $id, Content $content)
    {
        $this->setFormViewInfo($request);
        //Validation table value
        if(!$this->validateTable($this->custom_table, Define::AUTHORITY_VALUES_AVAILABLE_ACCESS_CUSTOM_VALUE)){
            return;
        }
        // if user doesn't have authority for target id data, show deny error.
        if (!Admin::user()->hasPermissionData($id, $this->custom_table->table_name)) {
            Checker::error();
            return false;
        }
        $this->AdminContent($content);
        $content->body($this->createShowForm($id));
        return $content;
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit(Request $request, $id, Content $content)
    {
        $this->setFormViewInfo($request);
        //Validation table value
        if(!$this->validateTable($this->custom_table, Define::AUTHORITY_VALUES_AVAILABLE_EDIT_CUSTOM_VALUE)){
            return;
        }
        // if user doesn't have authority for target id data, show deny error.
        if (!Admin::user()->hasPermissionData($id, $this->custom_table->table_name)) {
            Checker::error();
            return false;
        }
        // if user doesn't have edit permission, redirect to show
        $redirect = $this->redirectShow($id);
        if (isset($redirect)) {
            return $redirect;
        }

        $this->AdminContent($content);
        PluginInstaller::pluginPreparing($this->plugins, 'loading');
        $content->body($this->form($id)->edit($id));
        PluginInstaller::pluginPreparing($this->plugins, 'loaded');
        return $content;
    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update($id)
    {
        // call form using id
        return $this->form($id)->update($id);
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create(Request $request, Content $content)
    {
        $this->setFormViewInfo($request);
        //Validation table value
        if(!$this->validateTable($this->custom_table, Define::AUTHORITY_VALUES_AVAILABLE_EDIT_CUSTOM_VALUE)){
            return;
        }
        // if user doesn't have permission creating data, throw admin.dany error.
        if (!Admin::user()->hasPermissionTable($this->custom_table->table_name, Define::AUTHORITY_VALUES_AVAILABLE_EDIT_CUSTOM_VALUE)) {
            $response = response($this->AdminContent()->withError(trans('admin.deny')));
            Pjax::respond($response);
        }

        $this->AdminContent($content);
        PluginInstaller::pluginPreparing($this->plugins, 'loading');
        $content->body($this->form(null));
        PluginInstaller::pluginPreparing($this->plugins, 'loaded');
        return $content;
    }

    /**
     * for file delete function.
     */
    public function filedelete(Request $request, $id){
        //Validation table value
        if(!$this->validateTable($this->custom_table, Define::AUTHORITY_VALUES_AVAILABLE_EDIT_CUSTOM_VALUE)){
            return;
        }
        // if user doesn't have authority for target id data, show deny error.
        if (!Admin::user()->hasPermissionData($id, $this->custom_table->table_name)) {
            Checker::error();
            return false;
        }
        // if user doesn't have edit permission, redirect to show
        $redirect = $this->redirectShow($id);
        if (isset($redirect)) {
            return $redirect;
        }

        // get file delete flg column name
        $del_column_name = $request->input(Field::FILE_DELETE_FLAG);
        /// file remove
        $form = $this->form($id);
        $fields = $form->builder()->fields();
        // filter file
        $fields->filter(function ($field) use($del_column_name) {
            return $field instanceof Field\Embeds;
        })->each(function ($field) use($del_column_name, $id) {
            // get fields
            $embedFields = $field->fields();
            $embedFields->filter(function ($field) use($del_column_name) {
                return $field->column() == $del_column_name;
            })->each(function ($field) use($del_column_name, $id) {
                // get file path
                $obj = getModelName($this->custom_table)::find($id);
                $original = $obj->getValue($del_column_name, true);
                $field->setOriginal($obj->value);

                $field->destroy(); // delete file
                \Exceedone\Exment\Model\File::deleteFileInfo($original); // delete file table
                $obj->setValue($del_column_name, null)
                    ->remove_file_columns($del_column_name)
                    ->save();
            });
        });

        return response([
            'status'  => true,
            'message' => trans('admin.update_succeeded'),
        ]);
    }

    //Function handle click event
    /**
     * @param Request $request
     * @return Response
     */
    public function pluginClick(Request $request, $id = null)
    {
        if ($request->input('uuid') === null) {
            abort(404);
        }
        // get plugin
        $plugin = Plugin::where('uuid', $request->input('uuid'))->first();
        if(!isset($plugin)){
            abort(404);
        }
        
        $classname = getPluginNamespace(array_get($plugin, 'plugin_name'), 'Plugin');
        if (class_exists($classname)) {
            switch(array_get($plugin, 'plugin_type')){
                case 'document':
                    $class = new $classname($this->custom_table, $id);
                    break;
            }
            $class->execute();
        }
        return Response::create('Plugin Called', 200);
    }
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $classname = $this->getModelNameDV();
        $grid = new Grid(new $classname);
        PluginInstaller::pluginPreparing($this->plugins, 'loading');
        
        // get search_enabled_columns and loop
        $search_enabled_columns = getSearchEnabledColumns($this->custom_table->table_name);
    
        // create grid
        $this->createGrid($grid);

        // manage row action
        $this->manageRowAction($grid);

        // filter
        Admin::user()->filterModel($grid->model(), $this->custom_table->table_name, $this->custom_view);
        $this->setCustomGridFilters($grid, $search_enabled_columns);

        // manage tool button
        $listButton = PluginInstaller::pluginPreparingButton($this->plugins, 'grid_menubutton');
        $this->manageMenuToolButton($grid, $listButton);

        // create exporter
        $grid->exporter(DataImportExport\DataExporterBase::getModel($grid, $this->custom_table, $search_enabled_columns));
        
        PluginInstaller::pluginPreparing($this->plugins, 'loaded');
        return $grid;
    }

    /**
     * Make a form builder.
     * @param $id if edit mode, set model id
     * @return Form
     */
    protected function form($id = null)
    {
        $this->setFormViewInfo(\Request::capture());

        $classname = $this->getModelNameDV();
        $form = new Form(new $classname);

        //PluginInstaller::pluginPreparing($this->plugins, 'loading');
        // create
        if (!isset($id)) {
            $isButtonCreate = true;
            $listButton = PluginInstaller::pluginPreparingButton($this->plugins, 'form_menubutton_create');
        }
        // edit
        else {
            $isButtonCreate = false;
            $listButton = PluginInstaller::pluginPreparingButton($this->plugins, 'form_menubutton_edit');
        }

        //TODO: escape laravel-admin bug.
        //https://github.com/z-song/laravel-admin/issues/1998
        $form->hidden('laravel_admin_escape');

        // add parent select if this form is 1:n relation
        $relation = CustomRelation
            ::with('parent_custom_table')
            ->where('child_custom_table_id', $this->custom_table->id)
            ->where('relation_type', Define::RELATION_TYPE_ONE_TO_MANY)
            ->first();
        if(isset($relation)){
            $parent_custom_table = $relation->parent_custom_table;
            $form->hidden('parent_type')->default($parent_custom_table->table_name);

            // set select options
            if(isGetOptions($parent_custom_table)){
                $form->select('parent_id', $parent_custom_table->table_view_name)
                ->options(function($value) use($parent_custom_table){
                    return getOptions($parent_custom_table, $value);
                })
                ->rules('required');
            }else{
                $form->select('parent_id', $parent_custom_table->table_view_name)
                ->options(function($value) use($parent_custom_table){
                    return getOptions($parent_custom_table, $value);
                })
                ->ajax(getOptionAjaxUrl($parent_custom_table))
                ->rules('required');
            }
        }

        // loop for custom form blocks
        foreach ($this->custom_form->custom_form_blocks as $custom_form_block) {
            // if available is false, continue
            if (!$custom_form_block->available) {
                continue;
            }
            // when default block, set as normal form columns.
            if ($custom_form_block->form_block_type == Define::CUSTOM_FORM_BLOCK_TYPE_DEFAULT) {
                //$form->embeds('value', $this->custom_form->form_view_name, function (Form\EmbeddedForm $form) use($custom_form_block) {
                $form->embeds('value', exmtrans("common.input"), function (Form\EmbeddedForm $form) use ($custom_form_block, $id) {
                    $this->setCustomFormColumns($form, $custom_form_block, $id);
                });
            } elseif ($custom_form_block->form_block_type == Define::CUSTOM_FORM_BLOCK_TYPE_RELATION_ONE_TO_MANY) {
                $target_table = $custom_form_block->target_table;
                // get label hasmany
                $block_label = $custom_form_block->form_block_view_name;
                if (!isset($block_label)) {
                    $block_label = exmtrans('custom_form.table_one_to_many_label') . $target_table->table_view_name;
                }
                // get form columns count
                $count = count($custom_form_block->custom_form_columns);
                $form_block_options = array_get($custom_form_block, 'options', []);
                $relation_name = getRelationNamebyObjs($this->custom_table, $target_table);
                // if form_block_options.hasmany_type is 1, hasmanytable
                if (boolval(array_get($form_block_options, 'hasmany_type'))) {
                    $form->hasManyTable(
                        $relation_name,
                        $block_label,
                        function ($form) use ($custom_form_block, $id) {
                            $form->nestedEmbeds('value', $this->custom_form->form_view_name, function (Form\EmbeddedForm $form) use ($custom_form_block, $id) {
                                $this->setCustomFormColumns($form, $custom_form_block, $id);
                            });
                        }
                    )->setTableWidth(12, 0);
                }
                // default,hasmany
                else{           
                    $form->hasMany(
                        $relation_name,
                        $block_label,
                        function ($form) use ($custom_form_block, $id) {
                            $form->nestedEmbeds('value', $this->custom_form->form_view_name, function (Form\EmbeddedForm $form) use ($custom_form_block, $id) {
                                $this->setCustomFormColumns($form, $custom_form_block, $id);
                            });
                        }
                    );         
                }
            // when many to many
            } else {
                $target_table = $custom_form_block->target_table;
                // get label hasmany
                $block_label = $custom_form_block->form_block_view_name;
                if (!isset($block_label)) {
                    $block_label = exmtrans('custom_form.table_many_to_many_label') . $target_table->table_view_name;
                }

                $field = new Field\Listbox(
                    getRelationNamebyObjs($this->custom_table, $target_table),
                    [$block_label]
                );
                $field->options(function ($select) use ($target_table) {
                    return getOptions($target_table, $select);
                });
                if (getModelName($target_table)::count() > 100) {
                    $field->ajax(getOptionAjaxUrl($target_table));
                }
                $form->pushField($field);
            }
        }

        $calc_formula_array = [];
        $changedata_array = [];
        $relatedlinkage_array = [];
        $this->setCustomFormEvents($calc_formula_array, $changedata_array, $relatedlinkage_array);

        // add calc_formula_array and changedata_array info
        if (count($calc_formula_array) > 0) {
            $json = json_encode($calc_formula_array);
            $script = <<<EOT
            var json = $json;
            Exment.CommonEvent.setCalcEvent(json);
EOT;
            Admin::script($script);
        }
        if (count($changedata_array) > 0) {
            $json = json_encode($changedata_array);
            $script = <<<EOT
            var json = $json;
            Exment.CommonEvent.setChangedataEvent(json);
EOT;
            Admin::script($script);
        }
        if (count($relatedlinkage_array) > 0) {
            $json = json_encode($relatedlinkage_array);
            $script = <<<EOT
            var json = $json;
            Exment.CommonEvent.setRelatedLinkageEvent(json);
EOT;
            Admin::script($script);
        }

        // add authority form 
        $this->setAuthorityForm($form);

        // add form saving and saved event
        $this->manageFormSaving($form);
        $this->manageFormSaved($form);

        $form->disableReset();

        $isNew = $this->isNew();
        $custom_table = $this->custom_table;
        $custom_form = $this->custom_form;

        $this->manageFormToolButton($form, $id, $isNew, $custom_table, $custom_form, $isButtonCreate, $listButton);
        return $form;
    }

    /**
     * setAuthorityForm.
     * if table is user, org, etc...., not set authority
     */
    protected function setAuthorityForm($form){
        // if ignore user and org, return
        if (in_array($this->custom_table->table_name, [Define::SYSTEM_TABLE_NAME_USER, Define::SYSTEM_TABLE_NAME_ORGANIZATION])) {
            return;
        }
        // if table setting is "one_record_flg" (can save only one record), return
        if (boolval($this->custom_table->one_record_flg)) {
            return;
        }

        // set addAuthorityForm
        $this->addAuthorityForm($form, Define::AUTHORITY_TYPE_VALUE);
    }

    /**
     * @return string
     */
    protected function getModelNameDV()
    {
        return getModelName($this->custom_table->table_name);
    }

    /**
     * Check whether user has edit permission
     */
    protected function redirectShow($id)
    {
        if (!Admin::user()->hasPermissionEditData($id, $this->custom_table->table_name)) {
            return redirect(admin_base_path("data/{$this->custom_table->table_name}/$id"));
        }
        return null;
    }
}
