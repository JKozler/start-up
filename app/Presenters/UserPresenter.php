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
            $this->redirect('User:panel');
        }

        if(array_key_exists( "user_name",$this->getUser()->roles)){
            $this->template->user = $this->getUser()->roles["user_name"];
        }else{
            $this->template->user = "Nepřihlášen";
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

            if(!$row = $this->database->fetch('SELECT * FROM user_ntor WHERE username = ?', $values->email)){
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
            $this->flashMessage('Špatné údaje','danger');
            $this->redirect('User:panel');
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

    public function renderInventorPage(): void{
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
