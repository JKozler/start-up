<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\UI;


final class HomepagePresenter extends Nette\Application\UI\Presenter
{
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

}
