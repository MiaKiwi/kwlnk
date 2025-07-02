<?php

namespace Miakiwi\Kwlnk\App;

use Miakiwi\Kwlnk\Models\Account;



trait HasCreationTracker
{
    /**
     * The date the object was created.
     * @var \DateTime|null
     */
    private ?\DateTime $created_at = null;

    /**
     * The ID of the account who created the object.
     * @var string|null
     */
    private ?string $created_by_id = null;



    /**
     * Set the creation date of the object.
     * @param \DateTime|null $created_at The creation date to set. If null, the current time will be used.
     * @return void
     */
    public function setCreatedAt(?\DateTime $created_at = null): void
    {
        $this->created_at = $created_at ?? new \DateTime();
    }



    /**
     * Set the ID of the account who created the object.
     * @param string $created_by_id The ID of the account to set.
     * @return void
     */
    public function setCreatorId(string|Account $creator): void
    {
        if ($creator instanceof Account) {
            $this->created_by_id = $creator->getId();
        } else {
            $this->created_by_id = $creator;
        }
    }



    /**
     * Get the creation date of the object.
     * @return \DateTime|null
     */
    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }



    /**
     * Get the creation date of the object formatted as a string.
     * @return string|null The creation date formatted as 'Y-m-d H:i:s', or null if the date is not set.
     */
    public function getCreatedAtFormatted(): ?string
    {
        return $this->created_at?->format('Y-m-d H:i:s');
    }



    /**
     * Get the ID of the account who created the object.
     * @return string|null
     */
    public function getCreatorId(): ?string
    {
        return $this->created_by_id;
    }



    /**
     * Get the account who created the object.
     * @return Account|null
     */
    public function getCreator(): ?Account
    {
        if ($this->getCreatorId() === null) {
            return null;
        }

        return Account::find($this->getCreatorId());
    }
}