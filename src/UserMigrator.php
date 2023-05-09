<?php

namespace Statamic\Migrator;

use Illuminate\Support\Facades\Validator;
use Statamic\Migrator\Exceptions\InvalidEmailException;

class UserMigrator extends Migrator
{
    use Concerns\GetsSettings,
        Concerns\MigratesContent,
        Concerns\MigratesFile,
        Concerns\MigratesRoles,
        Concerns\MigratesFieldsetsToBlueprints,
        Concerns\ThrowsFinalWarnings;

    protected $user;
    protected $fieldset;

    /**
     * Perform migration.
     */
    public function migrate()
    {
        $this
            ->parseUser()
            ->validateEmail()
            ->setNewPathWithEmailAsHandle()
            ->validateUnique()
            ->migrateUserSchema()
            ->saveMigratedYaml($this->user)
            ->migrateFieldset()
            ->throwFinalWarnings();
    }

    /**
     * Parse user.
     *
     * @return $this
     */
    protected function parseUser()
    {
        $this->user = $this->getSourceYaml("users/{$this->handle}.yaml");

        if ($this->getSetting('users.login_type') === 'email') {
            $this->user['email'] = $this->handle;
        }

        $this->fieldset = $this->files->exists($this->sitePath('settings/fieldsets/user.yaml'));

        return $this;
    }

    /**
     * Validate email is present on user to be used as new handle.
     *
     * @return $this
     *
     * @throws EmailRequiredException
     */
    protected function validateEmail()
    {
        if (Validator::make($this->user, ['email' => 'required|email'])->fails()) {
            throw new InvalidEmailException("A valid email is required to migrate user [{$this->handle}].");
        }

        return $this;
    }

    /**
     * Set new path to be used with new email handle.
     *
     * @return $this
     */
    protected function setNewPathWithEmailAsHandle()
    {
        $email = $this->user['email'];

        $this->newPath = base_path("users/{$email}.yaml");

        return $this;
    }

    /**
     * Migrate default v2 user schema to new schema.
     *
     * @return $this
     */
    protected function migrateUserSchema()
    {
        $user = collect($this->user);

        if ($user->has('first_name') || $user->has('last_name')) {
            $user['name'] = $user->only('first_name', 'last_name')->filter()->implode(' ');
        }

        if ($user->has('roles')) {
            $user['roles'] = $this->migrateRoles($user['roles']);
        }

        if ($user->has('groups')) {
            $user['groups'] = $this->migrateGroups($user['groups']);
        }

        $user = $user->except('first_name', 'last_name', 'email')->all();

        $this->user = $this->fieldset
            ? $this->migrateContent($user, 'user', false)
            : $user;

        return $this;
    }

    /**
     * Migrate fieldset.
     *
     * @return $this
     */
    protected function migrateFieldset()
    {
        if ($this->fieldset) {
            $this->migrateFieldsetToBlueprint(null, 'user');
        }

        return $this;
    }
}
