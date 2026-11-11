<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PermissionGroup;
use App\Http\Requests\PermissionGroupRequest;
use Illuminate\Support\Facades\Auth;

/**
 * This controller handles permission groups in the admin area, like creating or listing groups of permissions.
 */
class PermissionGroupController extends Controller
{
    /**
     * Shows a list of all active permission groups.
     *
     * Gets all permission groups that are active (status = 1) from the database
     * and sends them back as a JSON response.
     */
    public function index()
    {
        $permissionGroups = PermissionGroup::all()->where('status', 1);
        return $this->jsonSuccess($permissionGroups, 'Permission groups fetched successfully.');
    }

    /**
     * Shows a form to create a new permission group.
     *
     * Not used right now.
     */
    public function create()
    {
        //
    }

    /**
     * Saves a new permission group to the database.
     *
     * Checks if the input is correct using PermissionGroupRequest, then saves
     * a new permission group with its name, sets it as active, and records who created it.
     */
    public function store(PermissionGroupRequest $request)
    {
        $group = PermissionGroup::create([
            'name' => $request->group_name,
            'status' => 1,
            'created_by' => Auth::id(),
        ]);

        return $this->jsonSuccess($group, 'Permission group created successfully.');
    }

    /**
     * Shows details of a specific permission group.
     *
     * Not used right now.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Shows a form to edit a permission group.
     *
     * Not used right now.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Updates a permission group in the database.
     *
     * Not used right now.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Deletes a permission group from the database.
     *
     * Not used right now.
     */
    public function destroy(string $id)
    {
        //
    }
}