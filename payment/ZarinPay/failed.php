<?php

session_start();

if (!empty($_SESSION['authority']) && !empty($_SESSION['order_id'])) {
   $rootPath = filter_input(INPUT_SERVER, 'DOCUMENT_ROOT');
   $PHP_SELF = filter_input(INPUT_SERVER, 'PHP_SELF');
   $Pathfile = dirname(dirname($PHP_SELF, 2));
   $Pathfiles = $rootPath.$Pathfile;
   require_once $Pathfiles.'/config.php';

   $authority = $_SESSION['authority'];
   $order_id = $_SESSION['order_id'];

?>

   <!DOCTYPE html>
   <html lang="en">

   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>پرداخت نا موفق</title>


      <style>
         * {
            font-family: "vazir";
            direction: rtl;
         }

         .card {
            box-shadow: 0 15px 16.8px rgba(0, 0, 0, 0.031), 0 100px 134px rgba(0, 0, 0, 0.05);
            background-color: white;
            border-radius: 15px;
            padding: 35px;
         }

         .top {
            padding-bottom: 25px;
            min-width: 250px;
            text-align: center;
            border-bottom: dashed
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
            border-left: 0.18em dashed
            position: relative;
         }

         .top:before {
            background-color:
            position: absolute;
            content: "";
            display: block;
            width: 20px;
            height: 20px;
            border-radius: 100%;
            bottom: 0;
            right: -10px;
            margin-bottom: -10px;
         }

         svg,
         h3 {
            color:
         }

         svg {
            margin: 0 auto;
            width: 60px;
            height: 60px;
         }

         h3 {
            margin-top: 0px;
            margin-bottom: 10px;
         }

         span {
            color:
            font-size: 12px;
         }

         .bottom {
            text-align: center;
            margin-top: 30px;
         }

         .key-value {
            display: flex;
            justify-content: center;
         }

         .key-value span:first-child {
            font-weight: 0;
         }

         a {
            padding: 8px 20px;
            background-color:
            text-decoration: none;
            color: white;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 20px;
            display: block;
         }

         .outer-container {
            background-color:
            position: absolute;
            display: table;
            width: 100%;
            height: 100%;
            top: 0;
            right: 0;
         }

         .inner-container {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
         }

         .centered-content {
            display: inline-block;
            text-align: left;
            background:
            margin-top: 10px;
         }
      </style>

      <link href="https://cdnjs.cloudflare.com/ajax/libs/vazir-font/27.2.0/font-face.css" rel="stylesheet"
         type="text/css">


   </head>

   <body>
      <div class="outer-container">
         <div class="inner-container">
            <div class="card centered-content">
               <div class="top">

                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                     <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                        clip-rule="evenodd" />
                  </svg>
                  <h3>
                     پرداخـت نامـوفق!
                  </h3>
                  <span>شماره تراکنش: <?php echo htmlspecialchars($order_id, ENT_QUOTES, 'UTF-8'); ?></span>
               </div>
               <div class="bottom">
                  <div class="key-value">
                     <span>خرید ناموفق</span>
                  </div>
                  <a href="http://t.me/<?php echo htmlspecialchars($usernamebot, ENT_QUOTES, 'UTF-8'); ?>"> برگشت به ربات</a>
               </div>
            </div>

         </div>
      </div>
   </body>

   </html>

<?php

}

elseif(isset($_POST['authority']) && isset($_POST['order_id'])){

  $authority = $_POST['authority'];
  $order_id = $_POST['order_id'];

}

session_unset();
session_destroy();

?>


