<?PHP


session_start();
$_SESSION['sDate'] = $_POST["sDate"];
$_SESSION['eDate'] = $_POST["eDate"];

?>


<html>

<img src="images\customLoading.gif" alt="Loading" style=" display: block; margin-left: auto; margin-right: auto; vertical-align: middle; top: 50%; transform: translate(0, 150%); width:110px ">

</html>

<script type="text/javascript">
window.location.href = "ces.reports.page.php";
</script>