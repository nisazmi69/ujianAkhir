<?php
session_start();

abstract class Menu {
    protected $namaMenu, $harga;
    public function __construct($namaMenu, $harga) { $this->namaMenu = $namaMenu; $this->harga = $harga; }
    public function getNamaMenu() { return $this->namaMenu; }
    public function getHarga() { return $this->harga; }
    abstract public function getSpesifikasi();
}

class Kopi extends Menu {
    private $ukuran;
    public function __construct($namaMenu, $harga, $ukuran) { parent::__construct($namaMenu, $harga); $this->ukuran = $ukuran; }
    public function getSpesifikasi() { return "Ukuran: " . $this->ukuran; }
}

class NonKopi extends Menu {
    private $rasa;
    public function __construct($namaMenu, $harga, $rasa) { parent::__construct($namaMenu, $harga); $this->rasa = $rasa; }
    public function getSpesifikasi() { return "Rasa: " . $this->rasa; }
}

class User {
    private $nama, $poinMember;
    public function __construct($nama, $poinMember = 0) { $this->nama = $nama; $this->poinMember = $poinMember; }
    public function getNama() { return $this->nama; }
    public function getPoinMember() { return $this->poinMember; }
}

class Voucher {
    private $kodeVoucher, $diskon;
    public function __construct($kodeVoucher, $diskon) { $this->kodeVoucher = $kodeVoucher; $this->diskon = $diskon; }
    public function getDiskon() { return $this->diskon; }
}

class Pesanan {
    private $menu, $user, $voucher = null, $jumlah = 1; 
    
    public function __construct(User $user, Menu $menu, $jumlah = 1) { 
        $this->user = $user; 
        $this->menu = $menu; 
        $this->jumlah = $jumlah > 0 ? $jumlah : 1;
    }
    public function gunakanVoucher(Voucher $voucher) { $this->voucher = $voucher; }
    
    public function hitungTotal() {
        $totalAwal = $this->menu->getHarga() * $this->jumlah;
        if ($this->voucher !== null) { $totalAwal -= $this->voucher->getDiskon(); }
        return $totalAwal < 0 ? 0 : $totalAwal;
    }
    public function getJumlah() { return $this->jumlah; }
}

$masterMenu = [
    'Americano' => new Kopi('Americano', 15000, 'Regular'),
    'Latte' => new Kopi('Latte', 18000, 'Regular'),
    'Cappuccino' => new Kopi('Cappuccino', 20000, 'Large'),
    'Matcha Latte' => new NonKopi('Matcha Latte', 22000, 'Matcha')
];

$masterVoucher = [
    '123456' => new Voucher('123456', 2000),
    'OOP2026' => new Voucher('OOP2026', 5000) 
];

$showSummary = false; $currentOrder = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_order'])) {
    $namaInput = isset($_POST['customer_name']) && !empty(trim($_POST['customer_name'])) ? htmlspecialchars($_POST['customer_name']) : 'nisa';
    $menuSelect = isset($_POST['menu_select']) ? $_POST['menu_select'] : 'Americano';
    $voucherInput = isset($_POST['voucher_code']) ? trim($_POST['voucher_code']) : '';
    $jumlahInput = isset($_POST['jumlah_pesanan']) ? (int)$_POST['jumlah_pesanan'] : 1;
    
    $selectedMenu = isset($masterMenu[$menuSelect]) ? $masterMenu[$menuSelect] : $masterMenu['Americano'];
    $userObj = new User($namaInput, 1);
    
    $pesananObj = new Pesanan($userObj, $selectedMenu, $jumlahInput);

    if (!empty($voucherInput) && isset($masterVoucher[$voucherInput])) { $pesananObj->gunakanVoucher($masterVoucher[$voucherInput]); }
    
    $totalBayar = $pesananObj->hitungTotal();
    
    $currentOrder = [
        'nama' => $userObj->getNama(), 'menu' => $selectedMenu->getNamaMenu(), 'harga_awal' => $selectedMenu->getHarga(),
        'jumlah' => $pesananObj->getJumlah(), 'total_bayar' => $totalBayar, 'voucher' => !empty($voucherInput) ? $voucherInput : '-', 'poin' => $userObj->getPoinMember()
    ];
    
    if (!isset($_SESSION['riwayat_belanja'])) { $_SESSION['riwayat_belanja'] = []; }
    
    array_unshift($_SESSION['riwayat_belanja'], [ 'waktu' => date('H:i:s'), 'menu' => $selectedMenu->getNamaMenu() . " (x" . $pesananObj->getJumlah() . ")", 'harga' => $totalBayar ]);
    $showSummary = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KopiNesia</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: #f1f5f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; font-family: sans-serif; }
        .app-container { background-color: #ffffff; width: 100%; max-width: <?php echo $showSummary ? '850px' : '450px'; ?>; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); padding: 40px; display: grid; grid-template-columns: <?php echo $showSummary ? '1.1fr 1fr' : '1fr'; ?>; gap: <?php echo $showSummary ? '60px' : '0px'; ?>; transition: max-width 0.3s ease; }
        .order-form-section { display: flex; flex-direction: column; gap: 18px; }
        .title-brand, .section-title { font-size: 15px; font-weight: bold; color: #0f172a; margin-bottom: 5px; }
        .section-title { font-size: 13px; margin-bottom: 12px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group label { font-size: 11px; color: #94a3b8; font-weight: 500; }
        .form-control { width: 100%; padding: 8px 0; border: none; border-bottom: 1px solid #e2e8f0; font-size: 12px; color: #334155; background-color: transparent; outline: none; }
        .form-control:focus { border-bottom-color: #0f172a; }
        .select-wrapper { position: relative; }
        .select-wrapper::after { content: "▼"; font-size: 8px; color: #64748b; position: absolute; right: 5px; top: 35%; pointer-events: none; }
        select.form-control { appearance: none; -webkit-appearance: none; cursor: pointer; padding-right: 20px; }
        .btn-order { background-color: #1e293b; color: #ffffff; border: none; border-radius: 20px; padding: 10px; font-size: 12px; font-weight: 600; cursor: pointer; margin-top: 15px; width: 100%; }
        .btn-order:hover { background-color: #0f172a; }
        .summary-history-section { display: flex; flex-direction: column; gap: 28px; }
        .summary-card { display: flex; flex-direction: column; gap: 8px; font-size: 12px; }
        .summary-item { display: flex; justify-content: flex-start; }
        .summary-item .label { font-weight: bold; width: 100px; color: #0f172a; }
        .summary-item .value { color: #475569; }
        .badge-member { display: inline-block; background-color: #1e293b; color: #ffffff; font-size: 9px; font-weight: bold; padding: 3px 8px; border-radius: 4px; margin-top: 6px; align-self: flex-start; text-transform: uppercase; }
        .history-container { display: flex; flex-direction: column; gap: 8px; max-height: 200px; overflow-y: auto; }
        .history-row { display: flex; justify-content: space-between; font-size: 11px; color: #334155; }
        .history-left { display: flex; gap: 8px; }
        .history-time { color: #94a3b8; }
        .history-price { font-weight: 500; color: #0f172a; }
    </style>
</head>
<body>

<div class="app-container">
    <div class="order-form-section">
        <div class="title-brand">Coffee System Modern UI</div>
        <form action="" method="POST">
            <input type="hidden" name="proses_order" value="1">
            <div class="form-group"><input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="Nama customer" required></div>
            <div class="form-group" style="margin-top: 12px;">
                <div class="select-wrapper">
                    <select id="menu_select" name="menu_select" class="form-control">
                        <option value="Americano">Americano</option>
                        <option value="Latte">Latte</option>
                        <option value="Cappuccino">Cappuccino</option>
                        <option value="Matcha Latte">Matcha Latte</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top: 12px;">
                <div class="select-wrapper">
                    <select class="form-control"><option>Kopi</option><option>Non-Kopi</option></select>
                </div>
            </div>
            <div class="form-group" style="margin-top: 12px;">
                <div class="select-wrapper">
                    <select class="form-control"><option>Regular</option><option>Large</option><option>Jumbo</option></select>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 12px;">
                <input type="number" id="jumlah_pesanan" name="jumlah_pesanan" class="form-control" placeholder="Jumlah Pesanan" min="1" value="1" required>
            </div>
            
            <div class="form-group" style="margin-top: 12px;"><input type="text" id="voucher_code" name="voucher_code" class="form-control" placeholder="Voucher (Kata Kunci)"></div>
            <button type="submit" class="btn-order">Order Now</button>
        </form>
    </div>

    <?php if ($showSummary): ?>
    <div class="summary-history-section">
        <div>
            <div class="section-title">Order Summary</div>
            <div class="summary-card">
                <div class="summary-item"><span class="label">Nama:</span><span class="value"><?php echo $currentOrder['nama']; ?></span></div>
                <div class="summary-item"><span class="label">Menu:</span><span class="value"><?php echo $currentOrder['menu']; ?></span></div>
                <div class="summary-item"><span class="label">Harga Awal:</span><span class="value">Rp <?php echo number_format($currentOrder['harga_awal'], 0, ',', '.'); ?></span></div>
                
                <div class="summary-item"><span class="label">Jumlah:</span><span class="value"><?php echo $currentOrder['jumlah']; ?> Porsi</span></div>
                
                <div class="summary-item"><span class="label">Total Bayar:</span><span class="value">Rp <?php echo number_format($currentOrder['total_bayar'], 0, ',', '.'); ?></span></div>
                <div class="summary-item"><span class="label">Voucher:</span><span class="value"><?php echo $currentOrder['voucher']; ?></span></div>
                <div class="summary-item"><span class="label">Poin:</span><span class="value"><?php echo $currentOrder['poin']; ?></span></div>
                <span class="badge-member">Member</span>
            </div>
        </div>
        <div>
            <div class="section-title">Riwayat Transaksi</div>
            <div class="history-container">
                <?php if (isset($_SESSION['riwayat_belanja'])): ?>
                    <?php foreach ($_SESSION['riwayat_belanja'] as $log): ?>
                        <div class="history-row">
                            <div class="history-left"><span class="history-time"><?php echo $log['waktu']; ?></span><span class="history-name">- <?php echo $log['menu']; ?></span></div>
                            <span class="history-price">Rp <?php echo number_format($log['harga'], 0, ',', '.'); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>