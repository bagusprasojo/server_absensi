<?php
namespace MyApp;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;
    public $conn_db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;

        if(!$this->conn_db = mysqli_connect("localhost","tsmarto", "Noki@3310","smart_building")) {
            die('No connection 1: ' . mysqli_connect_error());
        } else {
            echo "Koneksi DB Berhasil\n";
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Koneksi baru! ({$conn->resourceId})\n";

        $sql = "select id,nip,nama,jabatan,perusahaan, tgl_hadir from undangan where is_hadir=1";
        $result = mysqli_query($this->conn_db, $sql, MYSQLI_USE_RESULT);

        if ($result) {
            while ($row = mysqli_fetch_row($result)) {
                $data = array(
                    'ID' => $row[0],
                    'NIP' => $row[1],
                    'Nama' => $row[2],
                    'Jabatan' => $row[3],
                    'Perusahaan' => $row[4],
                    'Tanggal' => $row[5],                        
                );

                $json[0] = $data;

                $j = json_encode($json);
                $conn->send($j);
            }            
        }                   
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $sql = "select id,nip,nama,jabatan,perusahaan, tgl_hadir from undangan where nip = '" . $msg . "';";
        $result = mysqli_query($this->conn_db, $sql, MYSQLI_USE_RESULT);

        foreach ($this->clients as $client) {
            echo "Pesan ({$msg})\n";
            if ($from !== $client) {
                $no = 0;
                if ($result) {
                    while ($row = mysqli_fetch_row($result)) {
                        $data = array(
                            'ID' => $row[0],
                            'NIP' => $row[1],
                            'Nama' => $row[2],
                            'Jabatan' => $row[3],
                            'Perusahaan' => $row[4],
                            'Tanggal' => $row[5],                        
                        );

                        $json[$no] = $data;
                        $no++;
                    }

                    $j = json_encode($json);
                    $client->send($j);
                }
            }                   
        }

        mysqli_free_result($result);
        $sql = "update undangan set is_hadir = 1, tgl_hadir = now() where nip = '" . $msg . "'";
        echo $sql . "\n";
        
        if (mysqli_query($this->conn_db, $sql)) {
          echo "Record updated successfully";
        } else {
          echo "Error updating record: " . mysqli_error($conn);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Koneksi ditutup! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
