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
    private $table_ideas;
    private $table_obory;
    private $passwords;

    public function __construct(Nette\Database\Context $database, Nette\Security\Passwords $passwords)
    {
        $this->database = $database;
        $this->table_ntor = $database->table('user_ntor');
        $this->table_stor = $database->table('user_stor');
        $this->table_ideas = $database->table('ideas');
        $this->table_obory = $database->table('obory');
        $this->passwords = $passwords;
    }

    public function authenticate(array $credentials) : \Nette\Security\IIdentity{

        $userrow = $this->getByNtor($credentials[0]);
        $userrow2 = $this->getByStor($credentials[0]);

        if(!empty($userrow)) {

            $password = $userrow['password'];

            if(!$this->passwords->verify($credentials[1],$password)){
                throw new \Exception;
            } else
            return new Nette\Security\Identity($userrow['id'],['user_name'=>$userrow['username'],'who'=>"ntor"]); //id,role, informace, NTOR
        } else if(!empty($userrow2)) {

            $password2 = $userrow2['password'];

            if(!$this->passwords->verify($credentials[1],$password2)){
                throw new \Exception;
            } else
                return new Nette\Security\Identity($userrow2['id'],['user_name'=>$userrow2['username'],'who'=>"stor"]); //id,role, informace, STOR
        } else {
            throw new \Exception;
        }
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




    public function createIdea($id_ntor, $name, $castka, $reward,$easy, $full, $id_obory){

        $this->table_ideas->insert([
            'id_ntor'=>$id_ntor,
            'name'=>$name,
            'castka'=>$castka,
            'reward'=>reward,
            'easy'=>$easy,
            'full'=>$full,
            'id_obory'=>$id_obory
        ]);
    }


/*
    public function allMoney(){
        $money = $this->table2->sum('money');
        return $money;
    }

    public function firmaMoney($ico){
        $money = $this->table2->where('ico = ?',$ico)->sum('money');
        return $money;
    }

    public function topPrispevek($ico){
        $money = $this->table2->where('ico = ?',$ico)->max('money');
        return $money;
    }

    public function userInfo($id,$ico){
        $row = $this->table->where('id = ? AND ico = ?',$id,$ico);
        return $row;
    }
*/
}