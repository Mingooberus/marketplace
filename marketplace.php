<?php
class Database {
    private $conn;

    public function __construct($servername, $username, $password, $dbname) {
        $this->conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function executeQuery($query, $params = []) {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function quote($value) {
        return $this->conn->quote($value);
    }
}

function getDatabaseConnection() {
    $servername = "REPLACE";
    $username = "REPLACE";
    $password = "REPLACE";
    $dbname = "REPLACE";

    return new Database($servername, $username, $password, $dbname);
}

if (isset($_GET["action"])) {
    $db = getDatabaseConnection();

    if ($_GET["action"] === "getpage") {
        function selectPage($pageNumber, $steamID = "") {
            global $db;
            $amtPerPage = 7;
            $limit = $amtPerPage;
            $offset = $amtPerPage * ($pageNumber - 1);

            $query = ($steamID !== "")
                ? "SELECT * FROM `listings` WHERE units > unitsSold AND sellerSID != " . $db->quote($steamID) . " ORDER BY id DESC LIMIT $limit OFFSET $offset;"
                : "SELECT * FROM `listings` WHERE units > unitsSold ORDER BY id DESC LIMIT $limit OFFSET $offset;";

            return $db->executeQuery($query);
        }

        $page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
        $steamID = isset($_GET["steamid"]) ? $_GET["steamid"] : "";

        echo json_encode(selectPage($page, $steamID));
    } elseif ($_GET["action"] === "getitems") {
        $query = "SELECT * FROM `items` WHERE listingID = ? AND sold = false;";
        echo json_encode($db->executeQuery($query, [intval($_GET["listingid"])]));
    } elseif ($_GET["action"] === "getunclaimedsales" || $_GET["action"] === "getunclaimedpurchases" || $_GET["action"] === "getactivelistings" || $_GET["action"] === "getactiveitems") {
        $sid = $db->quote($_GET["sid"]);
        $cid = intval($_GET["cid"]);

        $query = ($_GET["action"] === "getunclaimedsales")
            ? "SELECT * FROM `items` WHERE ownerSID = $sid AND ownerCID = $cid AND sold = true AND sellerClaimed = false;"
            : ($_GET["action"] === "getunclaimedpurchases")
                ? "SELECT * FROM `items` WHERE buyerSID = $sid AND buyerCID = $cid AND sold = true AND buyerClaimed = false;"
                : ($_GET["action"] === "getactivelistings")
                    ? "SELECT * FROM `listings` WHERE sellerSID = $sid AND sellerCID = $cid AND units > unitsSold;"
                    : "SELECT * FROM `items` WHERE listingID = ? AND sold = false AND sellerClaimed = false;";

        echo json_encode($db->executeQuery($query, [intval($_GET["listingid"])]));
    } elseif ($_GET["action"] === "getpagecount") {
        $query = "SELECT COUNT(*) FROM `listings` WHERE units > unitsSold";
        $count = $db->executeQuery($query);
        echo ceil($count[0][0] / 7);
    } elseif ($_GET["action"] === "search" || $_GET["action"] === "catsearch") {
        $term = $db->quote("%" . $_GET["term"] . "%");
        $steamID = isset($_GET["steamid"]) ? $db->quote($_GET["steamid"]) : "";

        $query = ($_GET["action"] === "search")
            ? ($steamID !== "")
                ? "SELECT * FROM `listings` WHERE units > unitsSold AND sellerSID != $steamID AND itemName LIKE $term ORDER BY pricePerUnit ASC"
                : "SELECT * FROM `listings` WHERE units > unitsSold AND itemName LIKE $term ORDER BY pricePerUnit ASC"
            : ($steamID !== "")
                ? "SELECT * FROM `listings` WHERE units > unitsSold AND category = ? AND sellerSID != $steamID ORDER BY pricePerUnit ASC LIMIT ? OFFSET ?"
                : "SELECT * FROM `listings` WHERE units > unitsSold AND category = ? ORDER BY pricePerUnit ASC LIMIT ? OFFSET ?";

        $page = intval($_GET["page"]);
        $limit = 7;
        $offset = $limit * ($page - 1);

        echo json_encode($db->executeQuery($query, [$cat, $limit, $offset]));
    }
}
?>
