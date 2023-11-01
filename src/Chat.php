<?php
namespace MyApp;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients = [];

    public $conn_db;

    public function __construct() {
        // $this->clients = new \SplObjectStorage;

        if(!$this->conn_db = mysqli_connect("localhost","tsmarto", "Noki@3310","smart_building")) {
            die('No connection 1: ' . mysqli_connect_error());
        } else {
            echo "Koneksi DB Berhasil\n";
        }
    }

    protected function addClient($clientData)
    {
        if (array_key_exists($clientData['id'], $this->clients)) {
            return;
        }

        $this->clients[$clientData['id']] = $clientData;
    }
    public function onOpen(ConnectionInterface $conn) {
        $data = [
            'id' => $conn->resourceId,
            'nickname' => 'user_' . $conn->resourceId,
            'logged_in' => false,
            'conn' => $conn
        ];

        $this->addClient($data);

        // $this->clients->attach($conn);
        echo "Koneksi baru! ({$conn->resourceId})\n";

        
    }

    private function sendTamuSudahHadir($from){
        
        $client = $this->getSender($from);

        $sql = "select id,nip,sapaan,nama,jabatan,perusahaan,text_to_speech,tgl_hadir from undangan where is_hadir=1";
        $result = mysqli_query($this->conn_db, $sql, MYSQLI_USE_RESULT);

        if ($result) {
            while ($row = mysqli_fetch_row($result)) {
                $data = array(
                    'ID' => $row[0],
                    'NIP' => $row[1],
                    'Sapaan' => $row[2],
                    'Nama' => $row[3],
                    'Jabatan' => $row[4],
                    'Perusahaan' => $row[5],
                    'TTS' => $row[6],
                    'Tanggal' => $row[7],                        
                );

                $json[0] = $data;

                $j = json_encode($json);
                $client['conn']->send($j);
                
            }            
        }                   
    }

    private function prosesMessagePrivate($destinations, $msg){
        foreach ($destinations as $index => $nickname) {
            foreach ($this->clients as $id => $client) {
                if ($client['nickname'] === $nickname) {
                    $client['conn']->send($msg);

                    echo "Msg " . $msg . " sent to " . $nickname . "\n";
                }
            }
        }

    }

    private function getSender($from){
        foreach ($this->clients as $id => $client) {
            if ($client['id'] === $from->resourceId) {
                return $client;
            }
        }

    }

    private function prosesMessage($from, $msg){

        $sender = $this->getSender($from);
        if ($sender['nickname'] === "checkin"){
            $sql = "update undangan set is_hadir = 1, tgl_hadir = now() where nip = '" . $msg . "'";
            echo $sql . "\n";
            
            if (mysqli_query($this->conn_db, $sql)) {
              echo "Record updated successfully \n";
            } else {
              echo "Error updating record: " . mysqli_error($conn);
            }
            
            $sql = "select id,nip,sapaan,nama,jabatan,perusahaan,text_to_speech,tgl_hadir from undangan where nip = '" . $msg . "';";
            $result = mysqli_query($this->conn_db, $sql, MYSQLI_USE_RESULT);

            if ($result) {
                $no = 0;
                while ($row = mysqli_fetch_row($result)) {
                    $data = array(
                        'ID' => $row[0],
                        'NIP' => $row[1],
                        'Sapaan' => $row[2],
                        'Nama' => $row[3],
                        'Jabatan' => $row[4],
                        'Perusahaan' => $row[5],
                        'TTS' => $row[6],
                        'Tanggal' => $row[7],                        
                    );

                    $json[$no] = $data;
                    $no++;
                }

                $j = json_encode($json);
                $this->prosesMessagePrivate(['display','display_depan'], $j);

                echo "Message to display sent\n";
            }
            
            mysqli_free_result($result);
            
        } else if ($sender['nickname'] === "puzzle_trigger"){
            echo "Masuk puzzle_trigger \n";
            $this->prosesMessagePrivate(["puzzle"], "Mainkan");
        } else if ($sender['nickname'] === "peresmian_trigger"){
            echo "Masuk peresmian_trigger \n";
            $this->prosesMessagePrivate(["puzzle"], "Mainkan");
        }

    }

    private function setNickName($from, $nickname){
        foreach ($this->clients as $id => $client) {
            if ((string)$id === (string)$from->resourceId) {
                $this->clients[$from->resourceId]['logged_in'] = true;
                $this->clients[$from->resourceId]['nickname'] = $nickname;

                echo "Nickname of ID " . $from->resourceId . " changed to " . $nickname . "\n";
            }
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        if (trim($msg) === '') {
            return;
        }        

        switch ($msg) {
            case trim($msg) === '/checkin':                
                $this->setNickName($from, 'checkin');
                break;

            case trim($msg) === '/display':                
                $this->setNickName($from, 'display');
                $this->sendTamuSudahHadir($from);
                break;

            case trim($msg) === '/display_depan':                
                $this->setNickName($from, 'display_depan');
                break;

            case trim($msg) === '/puzzle_trigger':                
                $this->setNickName($from, 'puzzle_trigger');
                break;

            case trim($msg) === '/puzzle':                
                $this->setNickName($from, 'puzzle');
                break;

            case trim($msg) === '/peresmian_trigger':                
                $this->setNickName($from, 'peresmian_trigger');
                break;

            default:
                $this->prosesMessage($from, $msg);
        } 



        
        
    }

    public function onClose(ConnectionInterface $conn) {        
        unset($this->clients[$conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
