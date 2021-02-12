<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\Json;
use Nette\SmartObject;
use Nette\Application\UI;
use Nette\Utils\Validators;
use Nette\Diagnostics\Debugger;
use Nette\Utils\DateTime;
use Sunra\PhpSimple\HtmlDomParser;
use Nette\Utils\FileSystem;
use Nette\Security\Passwords;
use Nette\Mail\Message;
use Nette\Security\IAuthenticator;
use App\Model\UserManager;
use \stdClass;

final class UserPresenter extends Nette\Application\UI\Presenter
{
    private $database;
    private $passwords;
    private $userManager;


    private $table_ntor;
    private $table_stor;
    private $searchString = "";
    private $searchObor = 11;

    public function __construct(Nette\Database\Context $database, Passwords $passwords, UserManager $userManager)
    {
        $this->database = $database;
        $this->passwords = $passwords;
        $this->table_ntor = $database->table('user_ntor');
        $this->table_stor = $database->table('user_stor');
        $this->userManager = $userManager;
    }

    /* MESSANGER */

    private $idSecond;
    private $idIdea;

    public function renderMess($idea, $ntor, $stor): void{

        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');

        $uid = $this->getUser()->id;

        if($this->getUser()->roles["who"] == 'ntor'){
            if($uid =! $ntor){
                $this->flashMessage('Přístup odepřen','danger');
                $this->redirect('User:');
            }

            $this->template->second = $this->database->table('user_stor')->where('id ?', $stor)->fetch()->username;
            //$this->template->ntorName = $this->getUser()->roles["user_name"];

            $this->idSecond = $stor;

            $session = $this->getSession();
            $sessionSection = $session->getSection('Messanger');
            $sessionSection->idSecond = $stor;
            $sessionSection->idIdea = $idea;
            $sessionSection->uid = $uid;


        } else if($this->getUser()->roles["who"] == 'stor'){

            if($uid =! $stor){
                $this->flashMessage('Přístup odepřen','danger');
                $this->redirect('User:');
            }

            //$this->template->storName = $this->getUser()->roles["user_name"];
            $this->template->second = $this->database->table('user_ntor')->where('id ?', $ntor)->fetch()->username;

            $this->idSecond = $ntor;

            /*$session = $this->getSession();
            $sessionSection = $session->getSection('Messanger');
            $sessionSection->idSecond = $ntor;
            $sessionSection->idIdea = $idea;
            $sessionSection->uid = $uid;*/

        } else{
            $this->flashMessage('Přístup odepřen #4','danger');
            $this->redirect('User:');
        }

        $this->idIdea = $idea;

        $this->template->zpravy = $this->database->table('messages')->where('id_idea ?', $idea)->where('id_stor ?', $stor);
        $this->template->userID = $this->getUser()->id;
    }

    protected function createComponentSendSearchForm(): Form
    {
        if($this->searchString == ""){

        }
        else{

        }
        $form = new Form;

        $form->addText('obsah', 'Hledat..')
            ->setHtmlAttribute('class', 'form-control text-center')
            ->addRule($form::MAX_LENGTH, 'Max je %d znaků', 256);

        $form->addSubmit('send', 'Odeslat')
            ->setHtmlAttribute('class', 'btn btn-success');

        $form->onSuccess[] = [$this, 'sendSearchFormSucceeded'];
        return $form;
    }

    protected function createComponentSendSearchPanelForm(): Form
    {
        $obory = $this->database->table('obory')->fetchPairs('id', 'name');
        $form = new Form;

        $form->addText('obsah', 'Hledat..')
            ->setHtmlAttribute('class', 'form-control text-center')
            ->addRule($form::MAX_LENGTH, 'Max je %d znaků', 256);

        $form->addSelect('obory', 'Obor:', $obory)
            ->setDefaultValue(11)
            ->setHtmlAttribute('class', 'form-control');

        $form->addSubmit('send', 'Vyhledat')
            ->setHtmlAttribute('class', 'btn btn-success');

        $form->onSuccess[] = [$this, 'sendSearchPanelFormSucceeded'];
        return $form;
    }

    protected function createComponentSendSearchZobrazeneForm(): Form
    {
        $form = new Form;

        $form->addText('obsah', 'Hledat..')
            ->setHtmlAttribute('class', 'form-control text-center')
            ->addRule($form::MAX_LENGTH, 'Max je %d znaků', 256);

        $form->addSubmit('send', 'Vyhledat')
            ->setHtmlAttribute('class', 'btn btn-success');

        $form->onSuccess[] = [$this, 'sendSearchZobrazeneFormSucceeded'];
        return $form;
    }

    public function sendSearchFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        $this->searchString = $values->obsah;
        $this->renderInventor();
    }

    public function sendSearchZobrazeneFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        $this->searchString = $values->obsah;
        if($this->template->user = $this->getUser()->roles["who"] == 'stor')
            $this->renderInvolved();
        else
            $this->renderReaction();
    }

    public function sendSearchPanelFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        $this->searchString = $values->obsah;
        if($values->obory != 11)
            $this->searchObor = $values->obory;
        $this->renderPanel();
    }

    protected function createComponentSendMessageForm(): Form
    {
        $form = new Form;

        $form->addText('obsah', 'obsah:')
            ->setRequired()
            ->setDefaultValue('Type your message')
            ->setHtmlAttribute('class', 'form-control')
            ->addRule($form::MAX_LENGTH, 'Max je %d znaků', 510);

        $form->addHidden('userid')
            ->setDefaultValue($this->getUser()->id);

        $form->addSubmit('send', 'Odeslat')
            ->setHtmlAttribute('class', 'btn btn-success');

        $form->addProtection();
        $form->onSuccess[] = [$this, 'sendMessageFormSucceeded'];
        return $form;
    }

    public function sendMessageFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        $idea = $this->getParameter('idea');
        $ntor = $this->getParameter('ntor');
        $stor = $this->getParameter('stor');

        $session = $this->getSession();
        $sessionSection = $session->getSection('Messanger');
        //if(isset($sessionSection->idSecond) && isset($sessionSection->idIdea) && isset($sessionSection->uid)){
        if(isset($idea) && isset($ntor) && isset($stor)){

            $uid = $this->getUser()->id;

            if($values->userid != $ntor && $values->userid != $stor) {
                $this->flashMessage('Invalidni uzivatel','danger');
                $this->redirect('User:');

            }

            if($this->getUser()->roles["who"] == 'ntor'){

                $this->userManager->createMessage($idea,$ntor,$stor,$values->obsah,0);

                $this->redirect('User:mess',[$idea,$ntor,$stor]);

            } else {

                $this->userManager->createMessage($idea,$ntor,$stor,$values->obsah,1);

                $this->redirect('User:mess',[$idea,$ntor,$stor]);
            }



        } else {
            $this->flashMessage('Invalidni data','danger');
            $this->redirect('User:');

        }


    }

    /* INVENTOR PAGES */
    public function renderAdd(): void{

        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');


        if(!$this->template->user = $this->getUser()->roles["who"] == 'ntor')
            $this->redirect('User:panel');

    }

    public function renderEdit($i): void{

        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');


        if(!$this->template->user = $this->getUser()->roles["who"] == 'ntor')
            $this->redirect('User:panel');

        //$row = $this->database->fetch('SELECT * FROM ideas WHERE id = ?', $i);
        $idea = $this->userManager->getIdea($i);

        if($idea == false || $i == 0){

            $this->flashMessage('Nápad nenalezen','danger');
            $this->redirect('User:');

        }else {
            //$this->template->idea = $row;
            $this->template->r = $idea->zaplaceno;
            $this['ideaForm']->setDefaults($idea->toArray());
        }
    }

    public function renderInventor(): void{

        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');

        if(!$this->template->user = $this->getUser()->roles["who"] == 'ntor')
            $this->redirect('User:panel');
        
        if($this->searchString == ""){
            $this->template->vysledky = $this->database->table('ideas')->where('id_ntor ?', $this->getUser()->id);
            $this->template->noti = $this->database->table('idea_notification');
        }
        else {
            $this->template->vysledky = $this->database->query('SELECT * FROM ideas WHERE id_ntor=? and name=?', $this->getUser()->id, $this->searchString)->fetchAll();
            $this->template->noti = $this->database->table('idea_notification');
        }

    }

    public function renderPostup($i, $what, $ntor, $stor): void {
        $this->template->idea_Id = $i;
        $this->template->ntor = $ntor;
        $this->template->stor = $stor;
        $this->template->item = $this->database->table('items')->where('id ?', $i)->fetch();
        if($what == 1) {
            if($this->template->user = $this->getUser()->roles["who"] == 'ntor'){
                $this->database->query('UPDATE items SET', [
                    'ntor_Agree' => 0
                ], 'WHERE id=?', $i);
            }
            else if ($this->template->user = $this->getUser()->roles["who"] == 'stor'){
                $this->database->query('UPDATE items SET', [
                    'stor_Agree' => 0
                ], 'WHERE id=?', $i);
            }
            if ($this->template->user = $this->getUser()->roles["who"] == 'stor')
                $this->redirect('User:interested', $this->template->item->idea_Id, $ntor);
            else
                $this->redirect('User:interested', $this->template->item->idea_Id, $stor);
        }
        else if($what == 2) {
            if($this->template->user = $this->getUser()->roles["who"] == 'ntor'){
                $this->database->query('UPDATE items SET', [
                    'ntor_Agree' => 1
                ], 'WHERE id=?', $i);
            }
            else if ($this->template->user = $this->getUser()->roles["who"] == 'stor'){
                $this->database->query('UPDATE items SET', [
                    'stor_Agree' => 1
                ], 'WHERE id=?', $i);
            }
            if ($this->template->user = $this->getUser()->roles["who"] == 'stor')
                $this->redirect('User:interested', $this->template->item->idea_Id, $ntor);
            else
                $this->redirect('User:interested', $this->template->item->idea_Id, $stor);
        }
    }


    /* INVESTOR PAGES */
    public function renderInvolved(): void{

        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');


        if($this->template->user = $this->getUser()->roles["who"] == 'ntor')
            $this->redirect('User:inventor');

        if($this->searchString == ""){
            $this->template->ideas = $this->database->table('idea_notification')->where('stor_Id=?', $this->getUser()->id)->fetchAll();
        }
        if($this->searchString != ""){
            $this->template->ideas = $this->database->query('SELECT * FROM idea_notification WHERE project_name=? and stor_Id=?', $this->searchString, $this->getUser()->id)->fetchAll();
        }
    }

    public function renderPanel(): void{

        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');


        if($this->template->user = $this->getUser()->roles["who"] == 'ntor')
            $this->redirect('User:inventor');
        
        if($this->searchString == "" && $this->searchObor == 11){
            $this->template->vysledky = $this->database->table('ideas');
        }
        if($this->searchString != "" && $this->searchObor == 11){
            $this->template->vysledky = $this->database->query('SELECT * FROM ideas WHERE name=?', $this->searchString)->fetchAll();
        }
        if($this->searchString == "" && $this->searchObor != 11){
            $this->template->vysledky = $this->database->query('SELECT * FROM ideas WHERE id_obory=?', $this->searchObor)->fetchAll();
        }
        if($this->searchString != "" && $this->searchObor != 11){
            $this->template->vysledky = $this->database->query('SELECT * FROM ideas WHERE name=? and id_obory=?', $this->searchString, $this->searchObor)->fetchAll();
        }
    }

    public function renderInterested($i, $id): void{
        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');

        if($this->template->user = $this->getUser()->roles["who"] == 'stor'){
            $idea = $this->database->table('ideas')->where('id=?', $i)->fetch();
            $noti = $this->database->table('idea_notification')->where('idea_Id=?', $idea->id)->fetchAll();
            $stor = $this->database->table('user_stor')->where('id=?', $this->getUser()->id)->fetch();
            $ntor = $this->database->table('user_ntor')->where('id=?', $idea->id_ntor)->fetch();
            $much = 0;
            foreach ($noti as &$a) {
                if($a->nstor_Name != $stor->username)
                    $much++;
            }
            if($much == count($noti)){
                $this->database->query('INSERT INTO idea_notification', [
                    'stor_Id' =>  $this->getUser()->id,
                    'idea_Id' => $i,
                    'ntor_Id' => $idea->id_ntor,
                    'nstor_Name' => $stor->username,
                    'project_Name' => $idea->name,
                    'ntor_Name' => $ntor->username
                    
                ]);
            }
            $this->template->name = $ntor->username;
            $this->template->items = $this->database->table('items')->where('idea_Id=? AND ntor_Id=? AND stor_Id=?', $i, $id, $this->getUser()->id);
            $this->template->ntor = $id;
            $this->template->stor = $this->getUser()->id;
        }
        else {
            $stor = $this->database->table('user_stor')->where('id=?', $id)->fetch();
            $this->template->name = $stor->username;
            $this->template->items = $this->database->table('items')->where('idea_Id=? AND stor_Id=? AND ntor_Id=?', $i, $id, $this->getUser()->id);
            $this->template->stor = $id;
            $this->template->ntor = $this->getUser()->id;
        }


        //if($this->template->user = $this->getUser()->roles["who"] == 'ntor')
         //   $this->redirect('User:inventor');


        $row = $this->database->fetch('SELECT * FROM ideas WHERE id = ?', $i);
        //$row = $this->database->table('ideas')->where('is ?', $i);

        if($row == false || $i == 0){

            $this->flashMessage('Nápad nenalezen','danger');
            $this->redirect('User:');

        }else {
            $this->template->idea = $row;
        }

        //$this->template->vysledky = $this->database->table('ideas');
    }




    // NTOR
    public function renderReaction(): void {
        if($this->searchString == ""){
            $this->template->ideas = $this->database->table('idea_notification')->where('ntor_Id=?', $this->getUser()->id)->fetchAll();
        }
        if($this->searchString != ""){
            $this->template->ideas = $this->database->query('SELECT * FROM idea_notification WHERE project_name=? and ntor_Id=?', $this->searchString, $this->getUser()->id)->fetchAll();
        }
    }

    protected function createComponentRegistrationForm(): UI\Form
    {
        //https://doc.nette.org/cs/3.0/form-rendering

        $form = new UI\Form;
        $form->addEmail('email', 'E-mail')
            ->setRequired('Zadejte prosím firemní email')
            //->setDefaultValue('company@email.com')
            //->setEmptyValue('company@email.com')
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zvolte si heslo')
            ->addRule(UI\Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaky', 4)
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addPassword('passwordVerify', 'Heslo pro kontrolu:')
            ->setRequired('Zadejte prosím heslo ještě jednou pro kontrolu')
            ->addRule(UI\Form::EQUAL, 'Hesla se neshodují', $form['password'])
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addSubmit('login', 'Registrovat')
            ->setHtmlAttribute('class', 'btn btn-primary mt-3 mx-auto fbud');
        $form->onSuccess[] = [$this, 'registrationFormSucceeded'];
        return $form;
    }

    // volá se po úspěšném odeslání formuláře
    public function registrationFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        if(true){

            $httpRequest = $this->getHttpRequest();
            $ipp = $httpRequest->getRemoteAddress();

            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength  = strlen($characters);
            $randomToken = '';
            for ( $i = 0; $i <10; $i++){
                $randomToken .= $characters[rand(0,$charactersLength-1)];
            }

            $this->userManager->createNtor($values->email,$values->password,$ipp,$randomToken);

            /*$mail = new Message;
            $mail->setFrom('Scan <scan@.info>')
                ->addTo($values->email)
                ->setSubject('Aktivace účtu')
                ->setHTMLBody("<b>Vítá vás </b>, <br>Pro potvrzení emailu: <a href='https://.cz/user/ver?u=" . $randomToken . "'>kliknout zde</a>");

            $mailer = new Nette\Mail\SmtpMailer([
                'host' => 'server.cz',
                'username' => 'scan@.info',
                'password' => '',
                'secure' => 'ssl',
            ]);
            $mailer->send($mail);
             */


            $this->flashMessage('Registraci potvrdte emailem, zkontrolujte spam slozku.');
            $this->redirect('User:');
        }
        else {
            $this->flashMessage('Email není firemní a nebo účet vaší firmy již existuje','danger');
            $this->redirect('User:');
        }


    }

    // STOR
    protected function createComponentSregistrationForm(): UI\Form
    {
        //https://doc.nette.org/cs/3.0/form-rendering

        $form = new UI\Form;
        $form->addEmail('email', 'E-mail')
            ->setRequired('Zadejte prosím firemní email')
            //->setDefaultValue('company@email.com')
            //->setEmptyValue('company@email.com')
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zvolte si heslo')
            ->addRule(UI\Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaky', 4)
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addPassword('passwordVerify', 'Heslo pro kontrolu:')
            ->setRequired('Zadejte prosím heslo ještě jednou pro kontrolu')
            ->addRule(UI\Form::EQUAL, 'Hesla se neshodují', $form['password'])
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addSubmit('login', 'Registrovat')
            ->setHtmlAttribute('class', 'btn btn-primary mt-3 mx-auto fbud');
        $form->onSuccess[] = [$this, 'sregistrationFormSucceeded'];
        return $form;
    }

    // volá se po úspěšném odeslání formuláře
    public function sregistrationFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        if(true){

            $httpRequest = $this->getHttpRequest();
            $ipp = $httpRequest->getRemoteAddress();

            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength  = strlen($characters);
            $randomToken = '';
            for ( $i = 0; $i <10; $i++){
                $randomToken .= $characters[rand(0,$charactersLength-1)];
            }

            $this->userManager->createStor($values->email,$values->password,$ipp,$randomToken);

            /*$mail = new Message;
            $mail->setFrom('Scan <scan@.info>')
                ->addTo($values->email)
                ->setSubject('Aktivace účtu')
                ->setHTMLBody("<b>Vítá vás </b>, <br>Pro potvrzení emailu: <a href='https://.cz/user/ver?u=" . $randomToken . "'>kliknout zde</a>");

            $mailer = new Nette\Mail\SmtpMailer([
                'host' => 'server.cz',
                'username' => 'scan@.info',
                'password' => '',
                'secure' => 'ssl',
            ]);
            $mailer->send($mail);
             */


            $this->flashMessage('Registraci potvrdte emailem, zkontrolujte spam slozku.');
            $this->redirect('User:');
        }
        else {
            $this->flashMessage('Email není firemní a nebo účet vaší firmy již existuje','danger');
            $this->redirect('User:');
        }


    }





    public function renderDefault(): void{
        $this->template->logged = $this->getUser()->isLoggedIn();
        if ($this->getUser()->isLoggedIn())
        {

            if($this->template->user = $this->getUser()->roles["who"] == 'ntor')
                $this->redirect('User:inventor');
            else
                $this->redirect('User:panel');
        }

        if(array_key_exists( "user_name",$this->getUser()->roles)){
            $this->template->user = $this->getUser()->roles["user_name"];
        }else{
            $this->template->user = "Nepřihlášen";
        }
    }

    protected function createComponentPasswordForm(): Form
    {
        $form = new Form;

        $form->addPassword('passwordOld', 'Staré heslo:')
            ->setHtmlAttribute('class', 'form-control text-center')
            ->addRule($form::MAX_LENGTH, 'Text je příliš dlouhý', 255)
            ->setRequired();
        
        $form->addPassword('password', 'Nové heslo:')
            ->setHtmlAttribute('class', 'form-control text-center')
            ->setHtmlAttribute('placeholder', 'Zadejte nové heslo')
            ->addRule($form::MAX_LENGTH, 'Text je příliš dlouhý', 255)
            ->setRequired();
        
        $form->addPassword('passwordNewAgain', 'Nové heslo znovu:')
            ->setHtmlAttribute('class', 'form-control text-center')
            ->setHtmlAttribute('placeholder', 'Zadejte nové heslo znovu')
            ->addRule($form::MAX_LENGTH, 'Text je příliš dlouhý', 255)
            ->setRequired();
        
        $form->addSubmit('send', 'Aktualizovat')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->addProtection();
        $form->onSuccess[] = [$this, 'passwordFormSucceeded'];
        return $form;
    }

    public function passwordFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        if ($this->getUser()->isLoggedIn()) {
            if ($this->getUser()->roles["who"] == 'ntor')
            {
                $post = $this->database->query('UPDATE user_ntor SET password = ? WHERE id=?', $values->password, $this->getUser()->id);
            }
            else
            {
                $post = $this->database->table('user_stor')->get($this->getUser()->id);
                $post->update($values);
            }
            $this->flashMessage("User byl úspěšně updatován.", 'success');
            $this->redirect('User:account');
        }
    }

    protected function createComponentItemsForm(): Form
    {
        $form = new Form;

        $form->addText('name', 'Název/Jméno:')
            ->setHtmlAttribute('class', 'form-control')
            ->addRule($form::MAX_LENGTH, 'Text je příliš dlouhý', 255)
            ->setRequired();
        
        $form->addText('smallP', 'Krátký popis:')
            ->setHtmlAttribute('class', 'form-control')
            ->addRule($form::MAX_LENGTH, 'Text je příliš dlouhý', 255)
            ->setRequired();
        
        $form->addTextArea('fullP', 'Delší popis:')
            ->setRequired()
            ->setHtmlAttribute('class', 'form-control')
            ->addRule($form::MAX_LENGTH, 'Text je příliš dlouhý', 10000);
        
        $form->addSubmit('send', 'Přidat')
            ->setHtmlAttribute('class', 'btn btn-success');

        $form->addProtection();
        $form->onSuccess[] = [$this, 'itemFormSucceeded'];
        return $form;
    }

    public function renderAccount(): void
    {
        $this->template->logged = $this->getUser()->isLoggedIn();
        if ($this->getUser()->isLoggedIn())
        {
            if ($this->getUser()->roles["who"] == 'ntor')
            {
                $this->template->user = $this->database->table('user_ntor')->where('id=?', $this->getUser()->id)->fetch();
                $this->template->data = $this->database->table('ideas')->where('id_ntor=?', $this->getUser()->id)->count('*');
            }
            else
            {
                $this->template->user = $this->database->table('user_stor')->where('id=?', $this->getUser()->id)->fetch();
            }
        }
    }

    public function renderItems($i, $ntor, $stor): void {
        $this->template->idea_Id = $i;
        $this->template->ntor = $ntor;
        $this->template->stor = $stor;
    }

    public function itemFormSucceeded(UI\Form $form, \stdClass $item): void
    {
        $item_Id = $this->getParameter('i');
        $ntor = $this->getParameter('ntor');
        $stor = $this->getParameter('stor');
        if ($this->getUser()->isLoggedIn()) {
            $this->database->query('INSERT INTO items', [
                'name' => $item->name,
                'smallP' => $item->smallP,
                'fullP' => $item->fullP,
                'idea_Id' => $item_Id,
                'ntor_Agree' => 0,
                'stor_Agree' => 0,
                'ntor_Id' => $ntor,
                'stor_Id' => $stor
                
            ]);

            $this->flashMessage("Item byl úspěšně přidán.", 'success');
            if ($this->getUser()->roles["who"] == 'ntor')
                $this->redirect('User:interested', $item_Id, $stor);
            else
            $this->redirect('User:interested', $item_Id, $ntor);
        }
    }

    protected function createComponentIdeaForm(): Form
    {
        $form = new Form;

        $form->addText('name', 'Název:')
            ->setHtmlAttribute('class', 'form-control')
            ->setRequired();

        $form->addInteger('castka', 'Částka:')
            ->setDefaultValue(0)
            ->setRequired()
            ->setHtmlAttribute('class', 'form-control input-sm')
            ->addRule($form::RANGE, 'Částka musí být v rozsahu mezi %d a %d.', [0, 100000000]);


        //YEES
        $obory = $this->database->table('obory')->fetchPairs('id', 'name');


        $form->addSelect('id_obory', 'Obor:', $obory)
            ->setDefaultValue(1)
            ->setHtmlAttribute('class', 'form-control')
            ->setRequired();

        $form->addTextArea('reward', 'Co nabízíme:')
            ->setRequired()
            ->setHtmlAttribute('class', 'form-control input-sm')
            ->addRule($form::MAX_LENGTH, 'Text je příliš dlouhý', 255);

        $form->addTextArea('easy', 'O co jde:')
            ->setRequired()
            ->setHtmlAttribute('class', 'form-control input-sm')
            ->addRule($form::MAX_LENGTH, 'Text je příliš dlouhý', 255);

        $form->addTextArea('full', 'Všechny podrobnosti: (rozsáhle) (uvidí po zaplacení poplatku):')
            ->setRequired()
            ->setHtmlAttribute('class', 'form-control')
            ->addRule($form::MAX_LENGTH, 'Text je příliš dlouhý', 10000);


        $form->addSubmit('send', 'Přidat a zaplatit!')
            ->setHtmlAttribute('class', 'btn btn-primary mt-3 mx-auto fbud');

        $form->addSubmit('send2', 'Přidat!')
            ->setHtmlAttribute('class', 'btn btn-primary mt-3 mx-auto fbud');

        $form->addProtection();
        $form->onSuccess[] = [$this, 'ideaFormSucceeded'];
        return $form;
    }

    public function ideaFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        $this->template->logged = $this->getUser()->isLoggedIn();
        if ($this->getUser()->isLoggedIn()) {

            if ($this->template->user = $this->getUser()->roles["who"] == 'ntor') {

                $index = $this->getParameter('i');

                if($index){

                    $ideaI = $this->userManager->getIdea($index);
                    $ideaI->update((array)$values);
                    $this->flashMessage('Úspěšně aktualizováno');
                    $this->redirect('User:inventor');

                }else {
                    $this->userManager->createIdea($this->getUser()->id, $values->name, $values->castka, $values->reward, $values->easy, $values->full, $values->id_obory);
                    $this->flashMessage('Úspěšně vytvořeno');
                    $this->redirect('User:inventor');
                }


            }

        }



    }


    protected function createComponentSignInForm(): Form
    {
        $form = new Form;
        $form->addEmail('email', 'E-mail')
            ->setRequired('Zadejte prosím firemní email')
            //->setDefaultValue('company@email.com')
            //->setEmptyValue('company@email.com')
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zadejte heslo')
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addSubmit('send', 'Přihlásit se')
            ->setHtmlAttribute('class', 'btn btn-primary mt-3 mx-auto fbud');
        $form->addProtection();
        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        return $form;
    }

    public function signInFormSucceeded(Form $form, \stdClass $values): void{
        try{

            $err = false;

           // if(!$row = $this->database->fetch('SELECT * FROM user_ntor, user_stor WHERE user_ntor.username = ? OR user_stor.username = ?', $values->email, $values->email)){
            if(!$row = $this->database->fetch('SELECT * FROM user_ntor WHERE username = ? ', $values->email)){
                $err = true;
                //$this->flashMessage('Účet neexistuje','danger');
            } else if($row->ver > 0){
                $this->getUser()->login($values->email, $values->password);
                $this->flashMessage('Úspěšně přihlášeno');
                //$this->redirect('User:panel');
            }else {
                $this->flashMessage('Váš účet není zatím aktivován! Zkontrolujte prosím i SPAM složku ve vašem Mailu','warning');
                //$this->redirect('User:');
            }


            if(!$err) return; // pro jistotu
            if(!$row = $this->database->fetch('SELECT * FROM user_stor WHERE username = ? ', $values->email)){
                $this->flashMessage('Účet neexistuje','danger');
            } else if($row->ver > 0){
                $this->getUser()->login($values->email, $values->password);
                $this->flashMessage('Úspěšně přihlášeno');
                //$this->redirect('User:panel');
            }else {
                $this->flashMessage('Váš účet není zatím aktivován! Zkontrolujte prosím i SPAM složku ve vašem Mailu','warning');
                //$this->redirect('User:');
            }


        } catch(\Exception $e) {
            $this->flashMessage('Špatné údaje ' . $e,'danger');
            //$this->redirect('User:panel');
        }


    }


    public function actionOut(): void
    {
        $this->getUser()->logout();
        $this->flashMessage('Odhlášení bylo úspěšné.');
        $this->redirect('User:');
    }

    public function renderRegister(): void{
        $this->template->logged = $this->getUser()->isLoggedIn();
        if ($this->getUser()->isLoggedIn())
        {
            $this->redirect('User:panel');
        }

        if(array_key_exists( "user_name",$this->getUser()->roles)){
            $this->template->user = $this->getUser()->roles["user_name"];
        }else{
            $this->template->user = "Nepřihlášen";
        }
    }

    public function renderStorregister(): void{
        $this->template->logged = $this->getUser()->isLoggedIn();
        if ($this->getUser()->isLoggedIn())
        {
            $this->redirect('User:panel');
        }

        if(array_key_exists( "user_name",$this->getUser()->roles)){
            $this->template->user = $this->getUser()->roles["user_name"];
        }else{
            $this->template->user = "Nepřihlášen";
        }
    }

    public function renderVer($u): void{
        if($row = $this->database->fetch('SELECT * FROM user WHERE token = ?',$u)){

            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength  = strlen($characters);
            $randomToken = '';
            for ( $i = 0; $i <10; $i++){
                $randomToken .= $characters[rand(0,$charactersLength-1)];
            }

            $this->database->query('UPDATE user SET', [
                'ver' => 1,
                'token' => $randomToken
            ], 'WHERE token = ?',$u);

            $this->flashMessage('Account is now activated, You can log in!');
            $this->redirect('User:');
        }
    }

    public function renderReset(): void{
        if ($this->getUser()->isLoggedIn())
        {
            $this->redirect('User:panel');
        }

        if(array_key_exists( "user_name",$this->getUser()->roles)){
            $this->template->user = $this->getUser()->roles["user_name"];
        }else{
            $this->template->user = "Nepřihlášen";
        }
    }

    protected function createComponentResetPassForm(): Form
    {
        $form = new Form;
        $form->addEmail('email', 'E-mail')
            ->setRequired('Zadejte prosím firemní email')
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addSubmit('send', 'Změna hesla')
            ->setHtmlAttribute('class', 'btn btn-primary mt-3 mx-auto fbud');
        $form->addProtection();
        $form->onSuccess[] = [$this, 'resetPassFormSucceeded'];
        return $form;
    }

    public function resetPassFormSucceeded(Form $form, \stdClass $values): void{

        $e = $values->email;

        if(!$row = $this->database->fetch('SELECT * FROM user WHERE username = ?',$e)){
            $this->flashMessage('Účet neexistuje','danger');
            $this->redirect('User:');
        }
        else if($row->ver == 0){
            $this->flashMessage('Účet ještě nebyl ověřen, v případě problému kontaktuje zde: krystof.rohan@ict-group.cz','danger');
            $this->redirect('User:');
        }else{
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength  = strlen($characters);
            $randomToken = '';
            for ( $i = 0; $i <10; $i++){
                $randomToken .= $characters[rand(0,$charactersLength-1)];
            }

            $this->database->query('UPDATE user SET', [
                'token' => $randomToken,
                'password' =>'RESET'
            ], 'WHERE username = ?',$e);


            /*$mail = new Message;
            $mail->setFrom('Scan <scan@it-helpdesk.info>')
                ->addTo($e)
                ->setSubject('Reset Hesla')
                ->setHTMLBody("<b>Vaše heslo bylo vyžádáno k resetování</b>, <br>Pokud jste tak učinili, klikněte zde: <a href='https://scan.ict-group.cz/user/r?u=" . $randomToken . "'>resetovat heslo</a>");

            $mailer = new Nette\Mail\SmtpMailer([
                'host' => 'host.cz',
                'username' => 'a@a.a',
                'password' => 'fdsfsdf',
                'secure' => 'ssl',
            ]);
            $mailer->send($mail);*/

            $this->flashMessage('Resetovací kód byl zaslán na Váš email','warning');
            $this->redirect('User:');
        }

    }


    public function renderR($u): void{

        if(!$row = $this->database->fetch('SELECT * FROM user_ntor WHERE token = ?',$u)){
            $this->flashMessage('ERR 0e002','danger');
            $this->redirect('User:');
        }else {

            if($row->password == 'RESET'){
                $this->template->u = $u;
            }else {
                $this->flashMessage('ERR 1e001','danger');
                $this->redirect('User:');
            }


        }

    }

    protected function createComponentResetForm(): Form
    {
        $form = new Form;
        $form->addPassword('password', 'Nové heslo:')
            ->setRequired('Zadejte heslo')
            ->addRule(UI\Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaky', 4)
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addPassword('passwordVerify', 'Heslo pro kontrolu:')
            ->setRequired('Zadejte prosím heslo ještě jednou pro kontrolu')
            ->addRule(UI\Form::EQUAL, 'Hesla se neshodují', $form['password'])
            ->setHtmlAttribute('class', 'my_form-control');

        $form->addHidden('toyen')
            ->setRequired('ERR 0e003');

        $form->addSubmit('send', 'Změna hesla')
            ->setHtmlAttribute('class', 'btn btn-primary mt-3 mx-auto fbud');
        $form->addProtection();
        $form->onSuccess[] = [$this, 'resetFormSucceeded'];
        return $form;
    }

    public function resetFormSucceeded(Form $form, \stdClass $values): void{

        $row = $this->database->fetch('SELECT * FROM user WHERE token = ?',$values->toyen);
        if($row->password == "RESET"){

            $this->userManager->changePass($values->password,$values->toyen);
            $this->flashMessage('Heslo bylo změněno! ');
            $this->redirect('User:');

        }
        else {
            $this->flashMessage('ERR 1e002','danger');
            $this->redirect('User:');
        }

    }


}
