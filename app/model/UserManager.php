<?php
namespace App\Model;

use Nette;
use Nette\Security\IAuthenticator;
use Nette\Security\Passwords;
use Nette\Database\Context;
use Nette\Security\Identity;
use Nette\Security\AuthenticationException;

class UserManager implements IAuthenticator
{
    private $database;
    private $table_ntor;
    private $table_stor;
    private $passwords;

    public function __construct(Nette\Database\Context $database, Nette\Security\Passwords $passwords)
    {
        $this->database = $database;
        $this->table_ntor = $database->table('user_ntor');
        $this->table_stor = $database->table('user_stor');
        $this->passwords = $passwords;
    }

    public function authenticate(array $credentials) : \Nette\Security\IIdentity{

        $userrow = $this->getByNtor($credentials[0]);
        $userrow2 = $this->getByStor($credentials[0]);
        if(empty($userrow) && empty($userrow2)) {
            throw new \Exception;
        }

        $password = $userrow['password'];
        if(!$this->passwords->verify($credentials[1],$password)){

            if(!$this->passwords->verify($credentials[1],$userrow2['password'])){
                throw new \Exception;
            } else
                return new Nette\Security\Identity($userrow2['id'],['user_name'=>$userrow2['username']]); //id,role, informace, STOR

        }

        return new Nette\Security\Identity($userrow['id'],['user_name'=>$userrow['username']]); //id,role, informace, NTOR
    }

    public function getByNtor(string $username){
        return $this->table_ntor->select("*")->where(['username'=>$username])->fetch();
    }

    public function getByStor(string $username){
        return $this->table_stor->select("*")->where(['username'=>$username])->fetch();
    }

    public function createNtor(string $email, string $password, string $ipp, string $randomToken){

        $this->table_ntor->insert([
            'username'=>$email,
            'password'=>$this->passwords->hash($password),
            'ip' => $ipp,
            'token' => $randomToken
        ]);

        //exit;
    }

    public function createStor(string $email, string $password, string $ipp, string $randomToken){

        $this->table_stor->insert([
            'username'=>$email,
            'password'=>$this->passwords->hash($password),
            'ip' => $ipp,
            'token' => $randomToken
        ]);

        //exit;
    }

    public function changePass(string $password, string $u){

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength  = strlen($characters);
        $randomToken = '';
        for ( $i = 0; $i <10; $i++){
            $randomToken .= $characters[rand(0,$charactersLength-1)];
        }


        $this->database->query('UPDATE user SET', [
            'password'=>$this->passwords->hash($password),
            'token'=> $randomToken
        ], 'WHERE token = ?',$u);

    }
}