<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Bonfire
 *
 * An open source project to allow developers get a jumpstart their development of CodeIgniter applications
 *
 * @package   Bonfire
 * @author    Bonfire Dev Team
 * @copyright Copyright (c) 2011 - 2013, Bonfire Dev Team
 * @license   http://guides.cibonfire.com/license.html
 * @link      http://cibonfire.com
 * @since     Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Activities Reports Context
 *
 * Allows the administrator to view the activity logs.
 *
 * @package    Bonfire
 * @subpackage Modules_Activities
 * @category   Controllers
 * @author     Bonfire Dev Team
 * @link       http://guides.cibonfire.com/helpers/file_helpers.html
 *
 */
class Reports extends Admin_Controller
{
    private $permissionDeleteOwn        = 'Activities.Own.Delete';
    private $permissionSiteReportsView  = 'Site.Reports.View';
    private $permissionViewActivities   = 'Bonfire.Activities.View';
    private $permissionViewDate         = 'Activities.Date.View';
    private $permissionViewModule       = 'Activities.Module.View';
    private $permissionViewOwn          = 'Activities.Own.View';
    private $permissionViewUser         = 'Activities.User.View';

    private $hasPermissionViewDate;
    private $hasPermissionViewModule;
    private $hasPermissionViewOwn;
    private $hasPermissionViewUser;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();

		$this->auth->restrict($this->permissionSiteReportsView);
		$this->auth->restrict($this->permissionViewActivities);

		$this->lang->load('activities');
		$this->lang->load('datatable');

        $this->load->model('activities/activity_model');

		Assets::add_js(array(
            Template::theme_url('js/bootstrap.js'),
            Template::theme_url('js/jquery.dataTables.min.js'),
            Template::theme_url('js/bootstrap-dataTables.js'),
        ));
		Assets::add_js($this->load->view('reports/activities_js', null, true), 'inline');

		Assets::add_css(array(
            Template::theme_url('css/datatable.css'),
            Template::theme_url('css/bootstrap-dataTables.css'),
        ));

        // Check the permissions, store the results
        $this->hasPermissionViewUser   = has_permission($this->permissionViewUser);
        $this->hasPermissionViewModule = has_permission($this->permissionViewModule);
        $this->hasPermissionViewOwn    = has_permission($this->permissionViewOwn);
        $this->hasPermissionViewDate   = has_permission($this->permissionViewDate);

		if ($this->hasPermissionViewUser || $this->hasPermissionViewModule
            || $this->hasPermissionViewDate
           ) {
			Template::set_block('sub_nav', 'reports/_sub_nav');
		}

		Template::set('toolbar_title', lang('activity_title'));
	}//end __construct()

	/**
	 * Lists all log files and allows you to change the log_threshold.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function index()
	{
        Template::set('has_permission_view_date', $this->hasPermissionViewDate);
        Template::set('has_permission_view_module', $this->hasPermissionViewModule);
        Template::set('has_permission_view_user', $this->hasPermissionViewUser);
        Template::set('has_permission_view_own', $this->hasPermissionViewOwn);

		if ($this->hasPermissionViewUser || $this->hasPermissionViewModule
            || $this->hasPermissionViewDate
           ) {
			// Get top 5 modules
			Template::set('top_modules', $this->activity_model->findTopModules(5));

			// Get top 5 users and usernames
            Template::set('top_users', $this->activity_model->findTopUsers(5));

			Template::set('activities',
                $this->activity_model->where($this->activity_model->get_table() . '.' . $this->activity_model->get_deleted_field(), 0)
                                     ->find_all()
            );
			Template::set('users',
                $this->user_model->where($this->user_model->get_table() . '.' . $this->user_model->get_deleted_field(), 0)
                                 ->order_by('username', 'asc')
                                 ->find_all()
            );
			Template::set('modules', Modules::list_modules());
			Template::render();
		} elseif ($this->hasPermissionViewOwn) {
			$this->activity_own();
		}
	}//end index()

	/**
	 * Shows the activities for the specified user.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function activity_user()
	{
		if ($this->hasPermissionViewUser) {
            return $this->_get_activity();
		}

		$this->activityRestricted();
	}

	/**
	 * Shows the activities for the current user.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function activity_own()
	{
		if ($this->hasPermissionViewOwn) {
            return $this->_get_activity('activity_own', $this->current_user->id);
		}

		$this->activityRestricted();
	}

	/**
	 * Shows the activities for the specified module.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function activity_module()
	{
		if ($this->hasPermissionViewModule) {
			return $this->_get_activity('activity_module');
		}

        $this->activityRestricted();
	}

	/**
	 * Shows the activities before the specified date.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function activity_date()
	{
		if (has_permission('Activities.Date.View')) {
			return $this->_get_activity('activity_date');
		}

		$this->activityRestricted();
	}

	/**
	 * Deletes the entries in the activity log for the specified area.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function delete()
	{
		$action = $this->input->post("action");
		$which  = $this->input->post("which");

		$this->_delete_activity($action, $which);

		redirect(SITE_AREA . '/reports/activities');
	}

	//--------------------------------------------------------------------
	// !PRIVATE METHODS
	//--------------------------------------------------------------------

    /**
     * Redirect the user and set an error message
     *
     * @access private
     *
     * @return void
     */
    private function activityRestricted()
    {
        Template::set_message(lang('activity_restricted'), 'error');
        redirect(SITE_AREA . '/reports/activities');
    }

	/**
	 * Delete the entries in the activity log for the specified area.
	 *
	 * @access private
	 *
	 * @param string $action The area we are in
	 * @param string $which  A specific value to match, or "all"
	 *
	 * @return void
	 */
	private function _delete_activity($action, $which)
	{
        // This is before the permission check because the permission check
        // takes longer and depends on the value of $action
		if (empty($action)) {
			Template::set_message('Delete section not specified', 'error');
			return;
		}

		// Check for permission to delete this
		$permission = str_replace('activity_', '', $action);
		if ( ! has_permission('Activities.' . ucfirst($permission) . '.Delete')) {
			Template::set_message(lang('activity_restricted'), 'error');
			return;
		}

		if (empty($which)) {
			Template::set_message('Delete value not specified', 'error');
			return;
		}

		// Different delete where statement switch
		switch ($action) {
			case 'activity_date':
				$value = 'activity_id';
    			break;

			case 'activity_module':
				$value = 'module';
    			break;

			default:
				$value = 'user_id';
    			break;
		}

		// Set a default delete then check if delete "all" was chosen
        if ($value == 'activity_id') {
            $this->activity_model->where("{$value} <", $which);
        } else {
            $this->activity_model->where($value, $which);
        }

		if ($which == 'all') {
            $this->activity_model->where("{$value} !=", 'tsTyImbdOBOgwIqtb94N4Gr6ctatWVDnmYC3NfIfczzxPs0xZLNBnQs38dzBYn8');
		}

		// Check if they can delete their own stuff
        if ( ! has_permission($this->permissionDeleteOwn)) {
            $this->activity_model->where('user_id !=', $this->auth->user_id());
        }

        $affected = $this->activity_model->delete();
		if (is_numeric($affected)) {
			Template::set_message(sprintf(lang('activity_deleted'), $affected), 'success');
			$this->activity_model->log_activity($this->auth->user_id(), "deleted {$affected} activities", 'activities');
		} elseif (isset($affected)) {
			Template::set_message(lang('activity_nothing'), 'attention');
		} else {
			Template::set_message('Error : ' . $this->activity_model->error, 'error');
		}
	}//end _delete_activity()

	/**
	 * Gets all the activity based on parameters passed
	 *
	 * @access public
	 *
	 * @param string $which      Filter the activities by type
	 * @param bool   $find_value Value to filter by
	 *
	 * @return void
	 */
	private function _get_activity($which = 'activity_user', $find_value = false)
	{
        $postedWhichSelect = $this->input->post("{$which}_select");

		// Check if $find_value has anything in it
		if ($find_value === false) {
			$find_value = $postedWhichSelect == '' ? $this->uri->segment(5) : $postedWhichSelect;
		}

		if (isset($_POST['delete'])) {
			$this->_delete_activity($which, $find_value);
		}

        $activityDeletedField = $this->activity_model->get_deleted_field();
        $activityTable = $this->activity_model->get_table();
        $userDeletedField = $this->user_model->get_deleted_field();
        $userTable = $this->user_model->get_table();

		// Set a couple default variables
		$name = lang('activity_all');
		$options = array('all' => $name);
		switch ($which) {
			case 'activity_module':
				$modules = Modules::list_modules();
				foreach ($modules as $mod) {
					$options[$mod] = $mod;
					if ($find_value == $mod) {
						$name = ucwords($mod);
					}
				}
				$where = 'module';
                break;

			case 'activity_date':
				foreach ($this->activity_model->find_all_by($activityDeletedField, 0) as $e) {
					$options[$e->activity_id] = $e->created_on;
					if ($find_value == $e->activity_id) {
						$name = $e->created_on;
					}
				}
				$where = 'activity_id';
    			break;

			case 'activity_own':
			default:
				if ($this->hasPermissionViewUser) {
					foreach ($this->user_model->where("{$userTable}.{$userDeletedField}", 0)->find_all() as $e) {
						$options[$e->id] = $e->username;
						if ($find_value == $e->id) {
							$name = $e->username;
						}
					}
				} elseif ($this->hasPermissionViewOwn) {
					$options = array();
					$options[$this->auth->user_id()] = $this->auth->user()->username;
					$name = $this->auth->user()->username;
				}
				$where = 'user_id';
    			break;
		}

		// Set some vars for the view
		Template::set('vars', array(
			'which'			=> $which,
			'view_which'	=> ucwords(lang($which)),
			'name'			=> $name,
			'delete_action'	=> $where,
			'delete_id'		=> $find_value
		));

		// Apply the filter, if there is one
        $this->activity_model->order_by($where, 'asc');
        if (empty($find_value) || $find_value == 'all') {
            $total = $this->activity_model->count_by("{$activityTable}.{$activityDeletedField}", 0);
        } else {
			$where = ($where == 'activity_id') ? 'activity_id <' : $where;
			$total = $this->activity_model->where($where, $find_value)
                                          ->where("{$activityTable}.{$activityDeletedField}", 0)
                                          ->count_by($where, $find_value);

			// Set this again for use in the main query
			$this->activity_model->where($where, $find_value);
		}

		// Does user have permission to see own records?
        if ( ! $this->hasPermissionViewOwn) {
            $this->activity_model->where("{$activityTable}.user_id !=", $this->auth->user_id());
		}

		// Pagination
		$this->load->library('pagination');
		$offset = $this->input->get('per_page');
		$limit = $this->settings_lib->item('site.list_limit');
		$this->pager['base_url'] 			= current_url() . '?';
		$this->pager['total_rows'] 			= $total;
		$this->pager['per_page'] 			= $limit;
		$this->pager['page_query_string']	= true;
		$this->pagination->initialize($this->pager);

		// Get the activities
		$this->activity_model->select(array(
                                        'activity',
                                        'module',
                                        'activities.created_on AS created',
                                        'username',
                                     ))
                             ->where('activities.deleted', 0)
                             ->join('users', 'activities.user_id = users.id', 'left')
                             ->order_by('activity_id', 'desc') // Most recent on top
                             ->limit($limit, $offset);

        Template::set('activity_content', $this->activity_model->find_all());
		Template::set('filter', $postedWhichSelect);
		Template::set('select_options', $options);

        Template::set('has_permission_view_date', $this->hasPermissionViewDate);
        Template::set('has_permission_view_module', $this->hasPermissionViewModule);
        Template::set('has_permission_view_user', $this->hasPermissionViewUser);
        Template::set('has_permission_view_own', $this->hasPermissionViewOwn);

		Template::set_view('reports/view');
		Template::render();
	}//end _get_activity()
}//end class