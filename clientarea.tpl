                    <td>
                        <form action="clientarea.php?action=productdetails&id={$serviceid}" method="post" style="float: right" class="mg-form">
                            <input type="hidden" name="alias" value="lol" />
                            <input type="hidden" name="modaction" value="alias_delete" />
                            <input type="submit" value="del" class="btn-remove btn" />
                        </form>
                    </td>

<h1 align="left"n>Server info:</h1>
<hr>
<div align="left">
    <p>Server ID: {$sid}</p>
    <p>Server name: {$sname}</p>
    <p>Server state: <b>{$sstate}</b></p>
    <hr>
    <p>Core: {$Core}</p>
    <p>RAM: {$RAM}</p>
    <p>Disk: {$Disk}</p>
    <p>Operating System: {$OS}</p>
    <p>Creation date: {$creationdate}</p>
    <p>Modification date: {$modificationdate}</p>
    <hr>
    <p>Public IPv4: <b>{$publicipv4}</b></p>
    <p>Private IPv4: {$privateipv4}</p>
    <p>IPv6: <b>{$ipv6}</b></p>
    <hr>
    <p>Location: {$location}</p>
    <p>Allow STMP: {$sec_group}</p>
    <hr>
    <p>Default Username: {$username}</p>
    <p>Default Password: {$password}</p>
</div>

