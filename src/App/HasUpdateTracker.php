<?php

namespace Miakiwi\Kwlnk\App;

use Miakiwi\Kwlnk\Models\Account;



trait HasUpdateTracker
{
    /**
     * The date the object was last updated.
     * @var \DateTime|null
     */
    private ?\DateTime $updated_at = null;

    /**
     * The ID of the account who last updated the object.
     * @var string|null
     */
    private ?string $updated_by_id = null;



    /**
     * Set the update time and updater ID of the object.
     * @param Account|string|null $id The ID of the account who last updated the object. If null, the current security context ID will be used.
     * @return void
     */
    public function update(Account|string|null $id = null): void
    {
        // If an Account object is passed, get its ID.
        $id = $id instanceof Account ? $id->getId() : $id;



        $this->setUpdatedAt();
        $this->setUpdaterId($id ?? SecurityContext::Id());
    }



    /**
     * Set the last update time of the object.
     * @param \DateTime|null $updated_at The last update time to set. If null, the current time will be used.
     * @return void
     */
    public function setUpdatedAt(?\DateTime $updated_at = null): void
    {
        $this->updated_at = $updated_at ?? new \DateTime();
    }



    /**
     * Set the ID of the account who last updated the object.
     * @param string|\Miakiwi\Kwlnk\Models\Account $updater The ID or account object to set.
     * @return void
     */
    public function setUpdaterId(string|Account $updater): void
    {
        if ($updater instanceof Account) {
            $this->updated_by_id = $updater->getId();
        } else {
            $this->updated_by_id = $updater;
        }
    }



    /**
     * Get the last update time of the object.
     * @return \DateTime|null
     */
    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updated_at;
    }



    /**
     * Get the update date of the object formatted as a string.
     * @return string|null The update date formatted as 'Y-m-d H:i:s', or null if the date is not set.
     */
    public function getUpdatedAtFormatted(): ?string
    {
        return $this->updated_at?->format('Y-m-d H:i:s');
    }



    /**
     * Get the ID of the account who last updated the object.
     * @return string|null
     */
    public function getUpdaterId(): ?string
    {
        return $this->updated_by_id;
    }



    /**
     * Get the account who updated the object.
     * @return Account|null
     */
    public function getUpdater(): ?Account
    {
        if ($this->getUpdaterId() === null) {
            return null;
        }

        return Account::find($this->getUpdaterId());
    }
}