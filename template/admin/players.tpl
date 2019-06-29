{include file="admin/header.tpl"}
<b>Add Player</b>
<table>
<form action="" method="post">

<tr>


<td>Firstname:</td>
<td><input type="text" name="firstname" value="" size="20" maxlength="40"></td>
</tr>
<tr>
<td>Lastname:</td>
<td><input type="text" name="lastname" value="" size="20" maxlength="40"></td>
</tr>

<tr>
<td>Team:</td>
<td>
 <select name="teams">
 {foreach key=key item=value from=$teams}
    <option value="{$key}">{$value}</option>
 {/foreach}
 </select>
</td>
</tr>

<tr>
<td>
<input type="hidden" name="type" value="addplayer">
<input type="submit" value="Insert">
</td>
<td>
&nbsp;
</td>
</tr>

</form>
</table>

<br />
<b>Delete Player</b>

<table>
<form action="" method="post">

<tr>
<td>
<select name="player">
{foreach key=key item=value from=$players}
   <option value="{$key}">{$value}</option>
{/foreach}
</select>
</td>
</tr>

<tr>
<td>
<input type="hidden" name="type" value="deleteplayer">
<input type="submit" value="Delete">
</td>
</tr>

</form>
</table>

{include file="admin/footer.tpl"}
