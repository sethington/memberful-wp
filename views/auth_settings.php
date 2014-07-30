<div class="wrap">
	<?php memberful_wp_render('option_tabs', array('active' => 'auth_settings')); ?>
	<?php memberful_wp_render('flash'); ?>
	
<p><?php _e( "These are your current Memberful API settings. Use these to setup another Wordpress instance on the same Memberful account.", 'memberful' ); ?></p>


	<table class="widefat fixed" id="memberful-auth-settings-table">
		<thead>
			<tr>
				<th scope="col" class="manage-column"><?php _e( "Option", 'memberful' ); ?></th>
				<th scope="col" class="manage-column"><?php _e( "Value", 'memberful' ); ?></th>
			</tr>
		</thead>
		<tbody class="role-mapping">
			<?php foreach( $current_auth_settings as $option => $setting): ?>
			<tr>
				<td><?php echo $option; ?></td>
				<td><?php echo $setting; ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<br/>
	<div>
		Copy and paste the following for new instance setup: <br/>
		<textarea style="width:450px;height:150px;"><?php echo json_encode($current_auth_settings); ?></textarea>
	</div>

</div>
