<table>
	<tr>
		<th>BAN</th>
		<th>ID</th>
		<th>名前</th>
		<th>hash</th>
		<th>登録日時</th>
		<th>最終チェック日時</th>
	</tr>
	<?php foreach ($items as $item): ?>
	<tr>
		<td><?php echo $html->link('BAN', array('action' => 'ban', 'hash' => $item['Waiting']['hash'])); ?></td>
		<td><?php echo h($item['Waiting']['id']); ?></td>
		<td><?php echo h($item['Waiting']['name']); ?></td>
		<td><?php echo h($item['Waiting']['hash']); ?></td>
		<td><?php echo h($item['Waiting']['created']); ?></td>
		<td><?php echo h($item['Waiting']['last_checked']); ?></td>
	</tr>
	<?php endforeach; ?>
</table>
