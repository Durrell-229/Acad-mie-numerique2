<?php
/* ═══════════════════════════════════════════
   ACADÉMIE NUMÉRIQUE — API MySQL
   Hôte  : sql100.infinityfree.com
   Base  : if0_41079444_school
   User  : if0_41079444
═══════════════════════════════════════════ */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function db() {
    static $pdo;
    if (!$pdo) {
        try {
            $pdo = new PDO(
                "mysql:host=sql100.infinityfree.com;dbname=if0_41079444_school;charset=utf8mb4",
                "if0_41079444", "Leo19092007",
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die(json_encode(['ok'=>false,'error'=>'Connexion echouee : '.$e->getMessage()]));
        }
    }
    return $pdo;
}

function initTables() {
    db()->exec("CREATE TABLE IF NOT EXISTS an_users (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(180) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'eleve',
        pays VARCHAR(100) DEFAULT '',
        classe VARCHAR(100) DEFAULT '',
        photo LONGTEXT DEFAULT NULL,
        online TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS an_cours (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(200) NOT NULL,
        descr TEXT DEFAULT NULL,
        matiere VARCHAR(80) DEFAULT NULL,
        level VARCHAR(50) DEFAULT NULL,
        icon VARCHAR(10) DEFAULT '📚',
        color VARCHAR(20) DEFAULT '#0d1f3c',
        url VARCHAR(500) DEFAULT NULL,
        by_id BIGINT NOT NULL DEFAULT 0,
        by_name VARCHAR(120) DEFAULT '',
        files LONGTEXT DEFAULT NULL,
        videos LONGTEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS an_salles (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(200) NOT NULL,
        matiere VARCHAR(80) DEFAULT NULL,
        descr TEXT DEFAULT NULL,
        room VARCHAR(200) NOT NULL,
        icon VARCHAR(10) DEFAULT '🎓',
        by_id BIGINT NOT NULL DEFAULT 0,
        by_name VARCHAR(120) DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$action = $_GET['action'] ?? '';
$b = json_decode(file_get_contents('php://input'), true) ?? [];
initTables();
function ok($d){ echo json_encode(['ok'=>true,'data'=>$d]); }
function err($m){ echo json_encode(['ok'=>false,'error'=>$m]); }

switch ($action) {

    case 'get_users':
        ok(db()->query("SELECT id,name,email,role,pays,classe,photo,online,created_at FROM an_users")->fetchAll());
        break;

    case 'register':
        $name=$b['name']??''; $email=$b['email']??''; $pass=$b['password']??'';
        $role=$b['role']??'eleve'; $pays=$b['pays']??''; $cl=$b['classe']??''; $photo=$b['photo']??null;
        if(!$name||!$email||!$pass){err('Champs obligatoires manquants');break;}
        $c=db()->prepare("SELECT id FROM an_users WHERE email=?"); $c->execute([$email]);
        if($c->fetch()){err('Email deja utilise');break;}
        $h=password_hash($pass,PASSWORD_DEFAULT);
        db()->prepare("INSERT INTO an_users(name,email,password,role,pays,classe,photo)VALUES(?,?,?,?,?,?,?)")->execute([$name,$email,$h,$role,$pays,$cl,$photo]);
        $id=db()->lastInsertId();
        ok(['id'=>(int)$id,'name'=>$name,'email'=>$email,'role'=>$role,'pays'=>$pays,'classe'=>$cl,'photo'=>$photo,'online'=>0]);
        break;

    case 'login':
        $email=$b['email']??''; $pass=$b['password']??'';
        if(!$email||!$pass){err('Champs manquants');break;}
        $s=db()->prepare("SELECT * FROM an_users WHERE email=?"); $s->execute([$email]); $u=$s->fetch();
        if(!$u||!password_verify($pass,$u['password'])){err('Email ou mot de passe incorrect');break;}
        db()->prepare("UPDATE an_users SET online=1 WHERE id=?")->execute([$u['id']]);
        unset($u['password']); $u['online']=1; ok($u);
        break;

    case 'logout':
        $id=(int)($b['id']??0);
        if($id) db()->prepare("UPDATE an_users SET online=0 WHERE id=?")->execute([$id]);
        ok(null); break;

    case 'update_user':
        $id=(int)($b['id']??0); $name=$b['name']??''; $pays=$b['pays']??''; $photo=$b['photo']??null; $pass=$b['password']??'';
        if(!$id||!$name){err('Donnees manquantes');break;}
        if($pass) db()->prepare("UPDATE an_users SET name=?,pays=?,photo=?,password=? WHERE id=?")->execute([$name,$pays,$photo,password_hash($pass,PASSWORD_DEFAULT),$id]);
        else db()->prepare("UPDATE an_users SET name=?,pays=?,photo=? WHERE id=?")->execute([$name,$pays,$photo,$id]);
        $s=db()->prepare("SELECT id,name,email,role,pays,classe,photo,online FROM an_users WHERE id=?"); $s->execute([$id]); ok($s->fetch());
        break;

    case 'set_online':
        $id=(int)($b['id']??0); $on=(int)($b['online']??0);
        if($id) db()->prepare("UPDATE an_users SET online=? WHERE id=?")->execute([$on,$id]);
        ok(null); break;

    case 'delete_user':
        $id=(int)($b['id']??0);
        if($id) db()->prepare("DELETE FROM an_users WHERE id=?")->execute([$id]);
        ok(null); break;

    case 'get_cours':
        $rows=db()->query("SELECT * FROM an_cours ORDER BY created_at DESC")->fetchAll();
        foreach($rows as &$r){
            $r['files']=$r['files']?json_decode($r['files'],true):[];
            $r['videos']=$r['videos']?json_decode($r['videos'],true):[];
            $r['desc']=$r['descr']; $r['mat']=$r['matiere']; $r['by']=$r['by_name'];
        }
        ok($rows); break;

    case 'add_cours':
        db()->prepare("INSERT INTO an_cours(title,descr,matiere,level,icon,color,url,by_id,by_name,files,videos)VALUES(?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$b['title']??'',$b['desc']??'',$b['matiere']??'',$b['level']??'',$b['icon']??'📚',$b['color']??'#0d1f3c',$b['url']??'',$b['byId']??0,$b['byName']??'',json_encode($b['files']??[]),json_encode($b['videos']??[])]);
        $id=db()->lastInsertId();
        $s=db()->prepare("SELECT * FROM an_cours WHERE id=?"); $s->execute([$id]); $r=$s->fetch();
        $r['files']=json_decode($r['files'],true); $r['videos']=json_decode($r['videos'],true);
        $r['desc']=$r['descr']; $r['mat']=$r['matiere']; $r['by']=$r['by_name'];
        ok($r); break;

    case 'delete_cours':
        $id=(int)($b['id']??0);
        if($id) db()->prepare("DELETE FROM an_cours WHERE id=?")->execute([$id]);
        ok(null); break;

    case 'get_salles':
        $rows=db()->query("SELECT * FROM an_salles ORDER BY created_at DESC")->fetchAll();
        foreach($rows as &$r){ $r['mat']=$r['matiere']; $r['desc']=$r['descr']; $r['by']=$r['by_name']; }
        ok($rows); break;

    case 'add_salle':
        db()->prepare("INSERT INTO an_salles(name,matiere,descr,room,icon,by_id,by_name)VALUES(?,?,?,?,?,?,?)")
            ->execute([$b['name']??'',$b['matiere']??'',$b['desc']??'',$b['room']??'',$b['icon']??'🎓',$b['byId']??0,$b['byName']??'']);
        $id=db()->lastInsertId();
        $s=db()->prepare("SELECT * FROM an_salles WHERE id=?"); $s->execute([$id]); $r=$s->fetch();
        $r['mat']=$r['matiere']; $r['desc']=$r['descr']; $r['by']=$r['by_name'];
        ok($r); break;

    case 'delete_salle':
        $id=(int)($b['id']??0);
        if($id) db()->prepare("DELETE FROM an_salles WHERE id=?")->execute([$id]);
        ok(null); break;

    default: err('Action inconnue');
}
?>
