<?php


namespace Src\objects;

use mysqli;

class Stock {

    public function __construct(
        public mysqli $connection,
        private ?int $id = null,
        private ?string $name = null,
        private ?string $ownername = null,
        private ?int $number = null,
        private ?int $pricePerOne = null,
        private ?int $sellId = null
    ) {}

    public function readAll(): array {

        $stmt = $this->connection->prepare('SELECT stocks.id, stocks.name, stocks.short, MIN(sells.price_per_one) as price FROM stocks LEFT JOIN sells ON stocks.id = sells.stock_id WHERE 1 GROUP BY stocks.id');

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        $result = $stmt->get_result();

        $stmt = $this->connection->prepare('SELECT SUM(sells.number) as number FROM stocks LEFT JOIN sells ON stocks.id = sells.stock_id WHERE 1 GROUP BY stocks.id');

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }
        $result2 = $stmt->get_result();

        $returnValue = array();

        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $number = $result2->fetch_array(MYSQLI_ASSOC);
            $row['number'] =  $number['number'] ? $number['number'] : 0;
            array_push($returnValue, $row);
        }


        return $returnValue;
    }

    public function queryAll(): array {
        $stmt = $this->connection->prepare('SELECT stocks.id, stocks.name, stocks.short, MIN(sells.price_per_one) as price FROM stocks LEFT JOIN sells ON stocks.id = sells.stock_id WHERE stocks.name LIKE ? OR stocks.short LIKE ? GROUP BY stocks.id');

        $likeParam = "%$this->name%";

        $stmt->bind_param('ss', $likeParam, $likeParam);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        $result = $stmt->get_result();

        $stmt = $this->connection->prepare('SELECT SUM(sells.number) as number FROM stocks LEFT JOIN sells ON stocks.id = sells.stock_id WHERE stocks.name LIKE ? OR stocks.short LIKE ? GROUP BY stocks.id');

        $likeParam = "%$this->name%";

        $stmt->bind_param('ss', $likeParam, $likeParam);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        $result2 = $stmt->get_result();

        $returnValue = array();

        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $number = $result2->fetch_array(MYSQLI_ASSOC);
            $row['number'] =  $number['number'] ? $number['number'] : 0;
            array_push($returnValue, $row);
        }


        return $returnValue;
    }

    public function readOne(): array {
        $stmt = $this->connection->prepare('SELECT stocks.id, stocks.name, stocks.short, MIN(sells.price_per_one) as price FROM stocks LEFT JOIN sells ON stocks.id = sells.stock_id WHERE stocks.id = ? GROUP BY stocks.id');

        $stmt->bind_param('i', $this->id);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        $returnValue = $stmt->get_result()->fetch_array(MYSQLI_ASSOC);
        $returnValue['sells'] = array();

        $stmt = $this->connection->prepare('SELECT sells.price_per_one as price, sum(sells.number) as number FROM stocks LEFT JOIN sells ON stocks.id = sells.stock_id WHERE stocks.id = ? GROUP BY sells.price_per_one ORDER BY sells.price_per_one LIMIT 5');

        $stmt->bind_param('i', $this->id);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        $result = $stmt->get_result();
        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $row['number'] = (int)$row['number'];
            array_push($returnValue['sells'], $row);
        }
        return $returnValue;
    }
    //when force is set to true and there are not enough stocks(within given threshold) to buy as many as user ordered function buys as many as there are
    public function buy(bool $force=false): bool {
        $stmt = $this->connection->prepare('SELECT sells.id, stocks.name, stocks.short, sells.username, sells.number, sells.price_per_one as price FROM stocks INNER JOIN sells ON stocks.id = sells.stock_id WHERE sells.username <> ? AND stocks.id = ? ORDER BY price ASC');

        $stmt->bind_param('si', $this->ownername, $this->id);

        $stmt->execute();

        $allSells = array();
        $sum = 0;

        $result = $stmt->get_result();
        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            array_push($allSells, $row);
        }

        $stmt = $this->connection->prepare('SELECT money FROM users WHERE username = ?');

        $stmt->bind_param('s', $this->ownername);

        $stmt->execute();

        $money =  (int)$stmt->get_result()->fetch_array(MYSQLI_ASSOC)['money'];

        foreach ($allSells as $sell) {

            if ($sell['price'] > $this->pricePerOne && isset($this->pricePerOne) && $sum < $this->number) {
                if ( !$force ) {
                    http_response_code(200);
                    die(json_encode(array('message' => 'There are not enough stocks available without exceeding price per one threshold.', 'success' => false)));
                }
                break;
            }
            if ($sum + $sell['number'] >= $this->number) {
                $remainingStocks = $this->number - $sum;
                $money -= $remainingStocks * $sell['price'];
                $sum += $remainingStocks;
                break;
        }
            $sum += $sell['number'];
            $money -= $sell['number'] * $sell['price'];

        }

        if ($money < 0) {
            http_response_code(200);
            die(json_encode(array('message' => "You don't have enough money to buy all the stocks.", 'success' => false)));
        }

        $thereAreEnoughSells = ($sum === $this->number);

        if ((!$thereAreEnoughSells && !$force) || $sum === 0) {
            http_response_code(200);
            die(json_encode(array('message' => 'There are not enough sell orders to buy as many stocks as you need (use force prop to buy as many as there are, if you use force and still get this message there are no stocks meeting given criteria available)', 'success' => false)));
        }

        $stocksToBuy =  $sum;
        $iteration = 0;

        while ($stocksToBuy > 0) {

            if ($allSells[$iteration]['number'] <= $stocksToBuy) {

                $transactionValue = $allSells[$iteration]['number'] * $allSells[$iteration]['price'];
                $stocksToBuy -= $allSells[$iteration]['number'];

                $stmt = $this->connection->prepare('DELETE FROM sells WHERE id=?');

                $stmt->bind_param('i', $allSells[$iteration]['id']);

                if (!$stmt->execute()) {
                    http_response_code(500);
                    die(json_encode(array('message' => 'Unknown internal server error. Possibly messed some important things up. Nothing to be concerned about.', 'success' => false)));
                }

            }
            else {

                $numberToSet = $allSells[$iteration]['number'] - $stocksToBuy;

                $stmt = $this->connection->prepare('UPDATE sells SET `number`= ? WHERE id=?');

                $stmt->bind_param('ii', $numberToSet, $allSells[$iteration]['id']);


                $transactionValue = $stocksToBuy * $allSells[$iteration]['price'];
                $stocksToBuy = 0;

                if (!$stmt->execute()) {
                    http_response_code(500);
                    die(json_encode(array('message' => 'Unknown internal server error. Possibly messed some important things up. Nothing to be concerned about.', 'success' => false)));
                }
            }

            $stmt = $this->connection->prepare('UPDATE users SET money=money + ? WHERE username=?');



            $stmt->bind_param('is', $transactionValue, $allSells[$iteration]['username']);

            if (!$stmt->execute()) {
                http_response_code(500);
                die(json_encode(array('message' => $allSells[$iteration], 'success' => false)));
            }
            $iteration++;
        }
        $stmt = $this->connection->prepare('UPDATE users SET money=? WHERE username=?');

        $stmt->bind_param('is', $money, $this->ownername);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error. Possibly messed some important things up. Nothing to be concerned about.1', 'success' => false)));
        }

        $stmt = $this->connection->prepare('INSERT INTO ownership (`username`, `stock_id`, `number`) VALUES (?, ?, ?)');

        $stmt->bind_param('sii', $this->ownername, $this->id, $sum);

        return $stmt->execute();

     }

    public function sell(): bool {

        $stmt = $this->connection->prepare('SELECT ownership.id, stocks.name, stocks.short, ownership.number FROM stocks INNER JOIN ownership ON stocks.id = ownership.stock_id WHERE ownership.username = ? AND stocks.id = ?');

        $stmt->bind_param('si', $this->ownername, $this->id);

        $stmt->execute();

        $allOwnerships = array();
        $result = $stmt->get_result();
        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            array_push($allOwnerships, $row);
        }
        $sum = 0;
        foreach ($allOwnerships as $ownership) {
            $sum += $ownership['number'];
        }
        if ($sum < $this->number) {
            http_response_code(200);
            die(json_encode(array('message' => 'You do not have enough stocks', 'success' => false)));
        }
        $iteration = 0;
        $numberToDelete = $this->number;
        while ($numberToDelete > 0) {
            if ($allOwnerships[$iteration]['number'] < $numberToDelete) {

                $numberToDelete -= $allOwnerships[$iteration]['number'];

                $stmt = $this->connection->prepare('DELETE FROM ownership WHERE id=?');

                $stmt->bind_param('i', $allOwnerships[$iteration]['id']);
            }
            else {
                $numberToSet = $allOwnerships[$iteration]['number'] - $numberToDelete;
                $id = $allOwnerships[$iteration]['id'];

                $stmt = $this->connection->prepare('UPDATE ownership SET number=? WHERE id=?');

                $stmt->bind_param('ii', $numberToSet, $id);

                $numberToDelete = 0;
            }
            if (!$stmt->execute()) {
                http_response_code(500);
                die(json_encode(array('message' => 'Unknown internal server error. Possibly messed some important things up. Nothing to be concerned about.', 'success' => false)));
            }
            $iteration++;
        }
        $stmt = $this->connection->prepare('INSERT INTO `sells`(`username`, `stock_id`, `number`, `price_per_one`) VALUES (?, ?, ?, ?)');

        $stmt->bind_param('siii', $this->ownername, $this->id, $this->number, $this->pricePerOne);

        return $stmt->execute();
    }

    public function cancelSell(): bool {

        $stmt = $this->connection->prepare('SELECT sells.stock_id, sells.number FROM sells WHERE id=? AND USERNAME=?');

        $stmt->bind_param('is', $this->sellId, $this->ownername);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error.', 'success' => false)));
        }

        if (!$stmt->get_result()->fetch_array(MYSQLI_ASSOC)) {
            http_response_code(200);
            die(json_encode(array('message' => 'Error while deleting sell. Are you sure that you passed correct id?', 'success' => false)));
        }

        $result = $stmt->get_result()->fetch_array(MYSQLI_ASSOC);

        $stmt = $this->connection->prepare('DELETE FROM sells WHERE id=? AND USERNAME=?');

        $stmt->bind_param('is', $this->sellId, $this->ownername);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => "Unknown internal server error.  Might've messed a lot of things up", 'success' => false)));
        }
        $stmt = $this->connection->prepare('INSERT INTO ownership (`username`, `stock_id`, `number`) VALUES (?, ?, ?)');

        $stmt->bind_param('sii', $this->ownername, $result['stock_id'], $result['number']);

       return $stmt->execute();
    }

    public function owned(): array {

        $stmt = $this->connection->prepare('SELECT stocks.id, stocks.name, stocks.short, sum(ownership.number) as number FROM stocks INNER JOIN ownership ON stocks.id = ownership.stock_id WHERE ownership.username = ? GROUP BY stocks.id');

        $stmt->bind_param('s', $this->ownername);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        $returnValue = array('result' => array());
        $result = $stmt->get_result();

        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            array_push($returnValue['result'], $row);
        }

        return $returnValue;
    }

    public function beingSold(): array {

        $stmt = $this->connection->prepare('SELECT sells.id, stocks.id as stockID, stocks.name, stocks.short, sells.number, sells.price_per_one as price FROM stocks INNER JOIN sells ON stocks.id = sells.stock_id WHERE sells.username = ?');

        $stmt->bind_param('s', $this->ownername);

        if (!$stmt->execute()) {
            http_response_code(500);
            die(json_encode(array('message' => 'Unknown internal server error', 'success' => false)));
        }

        $returnValue = array('result' => array());
        $result = $stmt->get_result();

        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            array_push($returnValue['result'], $row);
        }

        return $returnValue;
    }

    public function setPricePerOne(int $ppo): void {
        $this->pricePerOne = $ppo;
    }


}