<?php namespace Clumsy\CMS\Controllers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Routing\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Lang;
use Cartalyst\Sentry\Facades\Laravel\Sentry;
use Clumsy\CMS\Controllers\AdminController;

class UsersController extends AdminController {

    public function __construct()
    {
		$this->beforeFilter('@checkPermissions');

        $this->namespace = '\Clumsy\CMS\Models';
        
        parent::__construct();
    }

	public function checkPermissions(Route $route, Request $request)
	{
		$user = Sentry::getUser();
		$requested_user_id = $route->getParameter('user');

		if (!$user->hasAccess('users')) {

			if (!in_array($route->getName(), array("{$this->admin_prefix}.user.edit", "{$this->admin_prefix}.user.update")) || $requested_user_id != $user->id) {

				return Redirect::route('{$this->admin_prefix}.user.edit', $user->id)->with(array(
					'alert_status'  => 'warning',
					'alert' => trans('clumsy::alerts.users.forbidden'),
				));
			}
		}
	}

	/**
	 * Display a listing of users
	 *
	 * @return Response
	 */
	public function index($data = array())
	{
		$data['items'] = Sentry::findAllUsers();

        $data['title'] = trans('clumsy::titles.users');

        return parent::index();
	}

	public function create($data = array())
	{
		$data['title'] = trans('clumsy::titles.new_user');

		$data['edited_user_id'] = 'new';
		$data['edited_user_group'] = '';

		return $this->edit($id = null, $data);
	}

	/**
	 * Store a newly created user in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		$model = $this->model();

		$rules = array_merge(
			$model::$rules,
			array(
				'password' => 'required|min:6|max:255',
				'confirm_password' => 'required|same:password',
			)
		);

		$rules['email'] .= '|unique:users';

		$validator = Validator::make($data = Input::all(), $rules);

		if ($validator->fails())
		{
			return Redirect::back()
				->withErrors($validator)
				->withInput()
                ->with(array(
                    'alert_status' => 'warning',
                    'alert' 	   => trans('clumsy::alerts.invalid'),
                ));
		}

        $new_user = Sentry::register(array(
            'first_name' => Input::get('first_name'),
            'last_name'  => Input::get('last_name'),
            'email'      => Input::get('email'),
            'password'   => Input::get('password'),
        ));

        // Auto-activate
		$new_user->attemptActivation($new_user->getActivationCode());

		$group = Sentry::findGroupByName(Input::get('group'));
		$new_user->addGroup($group);

		return Redirect::route("{$this->admin_prefix}.user.index")->with(array(
           'alert_status' => 'success',
           'alert'  	  => trans('clumsy::alerts.user.added'),
        ));
	}

	/**
	 * Display the specified user.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
        return Redirect::route("{$this->admin_prefix}.user.edit", $id);
	}

	public function edit($id, $data = array())
	{
		if ($id)
		{
			$data['item'] = Sentry::findUserById($id);

			if ($self = (Sentry::getUser()->id == $id)) {
				
				$data['supress_delete'] = true;
			}

	        $data['title'] = $self ? trans('clumsy::titles.profile') : trans('clumsy::titles.edit_user');

	        $data['edited_user_id'] = $id;
	        $data['edited_user_group'] = $data['item']->getGroups()->first()->name;
		}

		$groups = array_map(function($group)
		{
			return $group->name;

		}, Sentry::findAllGroups());

		$data['groups'] = array_combine($groups, array_map(function($group)
		{
		    if (Lang::has('clumsy::fields.roles.'.Str::lower(str_singular($group))))
		    {
		        return trans('clumsy::fields.roles.'.Str::lower(str_singular($group)));
		    }

		    return str_singular($group);

		}, $groups));

		return parent::edit($id, $data);
	}

	/**
	 * Update the specified user in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		$model = $this->model();

		$rules = $model::$rules;

		if ($new_password = (Input::has('new_password') && Input::get('new_password') != '')) {

			$rules['new_password'] = 'required|min:6|max:255';
			$rules['confirm_new_password'] = 'required|same:new_password';
		}

		$validator = Validator::make($data = Input::all(), $rules);

		if ($validator->fails())
		{
			return Redirect::back()
				->withErrors($validator)
				->withInput()
                ->with(array(
                    'alert_status' => 'warning',
                    'alert'  	   => trans('clumsy::alerts.invalid'),
                ));
		}

		if ($new_password) {

			$data['password'] = $data['new_password'];
		}
		unset($data['new_password']);
		unset($data['confirm_new_password']);

		$user = Sentry::findUserById($id);

		if (Input::has('group')) {

			$groups = Sentry::findAllGroups();

			foreach ($groups as $group) {

				$user->removeGroup($group);
			}

			$group = Sentry::findGroupByName(Input::get('group'));

			$user->addGroup($group);

			unset($data['group']);
		}

		$user->update($data);

        $url = URL::route("{$this->admin_prefix}.user.index");

		if (!$user->hasAccess('users')) {

			$url = URL::route("{$this->admin_prefix}.user.edit", $user->id);
		}

		return Redirect::to($url)->with(array(
           'alert_status' => 'success',
           'alert'  	  => trans('clumsy::alerts.user.updated'),
        ));
	}

	/**
	 * Remove the specified user from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		if (Sentry::getUser()->id == $id) {

			$status = 'warning';
			$message = trans('clumsy::alerts.user.suicide');

		} else {
			
			$user = Sentry::findUserById($id);

		    $user->delete();

			$status = 'success';
			$message = trans('clumsy::alerts.user.deleted');
		}

		return Redirect::route("{$this->admin_prefix}.user.index")->with(array(
           'alert_status' => $status,
           'alert'        => $message,
        ));
	}
}