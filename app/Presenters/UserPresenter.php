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

final class UserPresenter extends Nette\Application\UI\Presenter
{
    private $database;
    private $passwords;
    private $userManager;


    private $table_ntor;
    private $table_stor;

    public function __construct(Nette\Database\Context $database, Passwords $passwords, UserManager $userManager)
    {
        $this->database = $database;
        $this->passwords = $passwords;
        $this->table_ntor = $database->table('user_ntor');
        $this->table_stor = $database->table('user_stor');
        $this->userManager = $userManager;
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
            $this['ideaForm']->setDefaults($idea->toArray());
        }

    }

    public function renderInventor(): void{

        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');

        if(!$this->template->user = $this->getUser()->roles["who"] == 'ntor')
            $this->redirect('User:panel');

        $this->template->vysledky = $this->database->table('ideas')->where('id_ntor ?', $this->getUser()->id);

    }


    /* INVESTOR PAGES */
    public function renderInvolved(): void{

        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');


        if($this->template->user = $this->getUser()->roles["who"] == 'ntor')
            $this->redirect('User:inventor');
    }

    public function renderPanel(): void{

        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');


        if($this->template->user = $this->getUser()->roles["who"] == 'ntor')
            $this->redirect('User:inventor');

        $this->template->vysledky = $this->database->table('ideas');
    }

    public function renderInterested($i): void{



        if (!$this->getUser()->isLoggedIn())
            $this->redirect('User:');


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

    public function itemFormSucceeded(UI\Form $form, \stdClass $item): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $post = $this->database->table('items')->insert($item);

            $this->flashMessage("Item byl úspěšně přidán.", 'success');
            $this->redirect('User:interested');
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


        $form->addSelect('obor', 'Obor:', $obory)
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


        $form->addSubmit('send', 'Přidat a zaplatit! ⓘ')
            ->setHtmlAttribute('class', 'btn btn-primary mt-3 mx-auto fbud');

        $form->addSubmit('send2', 'Přidat! ⓘ')
            ->setHtmlAttribute('class', 'btn btn-light mt-3 mx-auto fbud');

        $form->addProtection();
        $form->onSuccess[] = [$this, 'ideaFormSucceeded'];
        return $form;
    }

    public function ideaFormSucceeded(UI\Form $form, \stdClass $values): void
    {
        $this->template->logged = $this->getUser()->isLoggedIn();
        if ($this->getUser()->isLoggedIn()) {

            if ($this->template->user = $this->getUser()->roles["who"] == 'ntor') {
                $this->userManager->createIdea($this->getUser()->id, $values->name, $values->castka, $values->reward, $values->easy, $values->full, $values->obor);
                $this->flashMessage('Úspěšně vytvořeno');
                $this->redirect('User:inventor');
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
                $this->redirect('User:panel');
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
