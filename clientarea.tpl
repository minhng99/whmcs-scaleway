{literal} 
<script language="javascript">
if ( window.history.replaceState ) {
  window.history.replaceState( null, null, window.location.href );
}
</script> 
{/literal}
{if $Action_Result}
  <hr>
  {if $Action_Result|strstr:"SUCCESS"}
    <div class="alert alert-success text-center" role="alert">
      {$Action_Result}
    </div>
  {else}
    <div class="alert alert-danger text-center" role="alert">
      {$Action_Result}
    </div>
  {/if}
{/if}

<hr>
<div class="panel panel-primary">
  <div class="panel-heading">
    <h3 class="panel-title" align="left">
      <span class="glyphicon glyphicon-tasks" aria-hidden="true"></span>
      Server Info
    </h3>
  </div>
  <div class="panel-body">
    <div align="left">
        <p>Server UUID: {$Server_UUID}</p>
        <p>Hostname: {$Hostname}</p>
        <p>Server State: <b>{$Server_State}</b></p>
        <hr>
        <p>Public IPv4: <span class="label label-primary">{$IPv4_Public}</span></p>
        <p>Private IPv4: {$IPv4_Private}</p>
        <p>IPv6: <span class="label label-primary">{$IPv6}</span></p>
        <p>Default Username: {$Username}</p>
        <p>Default Password: {$Password}</p>
        <hr>
        <p>CPU Core: {$CPU_Core}</p>
        <p>RAM: {$RAM}</p>
        <p>Disk: {$Disk}</p>
        <p>Operating System: <b>{$OS}</b></p>
        <hr>
        <p>Location: {$Location}</p>
        <p>Allow STMP: {$Security_Group}</p>
    </div>
  </div>
</div>

{if $Is_Running != -1}
  <hr>
  <div class="panel panel-warning">
    <div class="panel-heading">
      <h3 class="panel-title" align="left">
        <span class="glyphicon glyphicon-off" aria-hidden="true"></span>
        Power Management
      </h3>
    </div>
    <div class="panel-body">
      <form action="clientarea.php?action=productdetails&id={$Service_ID}" method="post">
        {if $Is_Running == 1}
          <button name="Power" type="submit" onclick="return confirm('Are you sure want to Reboot?');" class="btn btn-warning" value="Reboot">
            <span class="glyphicon glyphicon-repeat" aria-hidden="true"></span>
            Reboot
          </button>
          <button name="Power" type="submit" onclick="return confirm('Are you sure want to Stop?');" class="btn btn-danger" value="Stop">
            <span class="glyphicon glyphicon-stop" aria-hidden="true"></span>
            Stop
          </button>
        {elseif $Is_Running == 0}
          <button name="Power" type="submit" onclick="return confirm('Are you sure want to Start?');" class="btn btn-success" value="Start">
            <span class="glyphicon glyphicon-play" aria-hidden="true"></span>
            Start
          </button>
        {/if}
      </form>
    </div>
  </div>
{/if}

{if $Is_Running != -1}
  <hr>
  <div class="panel panel-warning">
    <div class="panel-heading">
      <h3 class="panel-title" align="left">
        <span class="glyphicon glyphicon-wrench" aria-hidden="true"></span>
        OS Installation
      </h3>
    </div>
    <div class="panel-body" align="left">
        <form action="clientarea.php?action=productdetails&id={$Service_ID}" method="post">
          <label>Select the OS that you want to switch or reinstall:</label>
          <div class="form-group">
            <select class="form-control" name="OS-Install">
              {$Available_OS}
            </select>
          </div>
          <div class="form-group">
            <button type="submit" onclick="return confirm('Are you sure want to install OS? ALL DATA WILL BE LOST');" class="btn btn-danger">
              <span class="glyphicon glyphicon-cog" aria-hidden="true"></span>
              Install
            </button>
          </div>
      </form>

    </div>
  </div>
{/if}

<hr>
<div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title" align="left">
      <span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span>
      Misc
    </h3>
  </div>
  <div class="panel-body" align="left">
      <form action="clientarea.php?action=productdetails&id={$Service_ID}" method="post">
        <label>Change Hostname:</label>
        <div class="form-group">
          <input name="New-Hostname" type="text" class="form-control" placeholder="{$Hostname}">
        </div>
        <div class="form-group">
          <button type="submit" onclick="return confirm('Are you sure want to change Hostname?');" class="btn btn-success">
            <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>
            Update Hostname
          </button>
        </div>
    </form>
  </div>
</div>
