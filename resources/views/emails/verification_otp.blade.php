<html>
<body style="background: #f6f6f6; font-family: Arial, sans-serif; margin: 0; padding: 0;">
	<table width="100%" bgcolor="#f6f6f6" cellpadding="0" cellspacing="0" style="padding: 30px 0;">
		<tr>
			<td align="center">
				<table width="100%" style="max-width: 480px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 32px 24px;">
					<tr>
						<td align="center" style="padding-bottom: 16px;">
							<img src="https://cdn-icons-png.flaticon.com/512/561/561127.png" width="64" height="64" alt="Verification" style="margin-bottom: 12px;">
							<h2 style="margin: 0; color: #333; font-size: 24px; font-weight: 700;">Email Verification</h2>
						</td>
					</tr>
					<tr>
						<td style="padding-bottom: 18px; color: #444; font-size: 16px;">
							Hello{{ isset($name) ? ', ' . $name : '' }},<br><br>
							Please use the following verification code to verify your email address:
						</td>
					</tr>
					<tr>
						<td align="center" style="padding-bottom: 18px;">
							<span style="display: inline-block; background: #007bff; color: #fff; font-size: 32px; letter-spacing: 8px; border-radius: 6px; padding: 12px 32px; font-weight: bold;">{{ $otp }}</span>
						</td>
					</tr>
					<tr>
						<td style="color: #666; font-size: 15px; padding-bottom: 10px;">
							Enter this code in the app to complete your registration.<br>
							<span style="color: #e55353;">This code will expire soon for your security.</span>
						</td>
					</tr>
					<tr>
						<td style="color: #888; font-size: 13px; padding-top: 12px; border-top: 1px solid #eee;">
							If you did not request this, please ignore this email.<br>
							<br>Thank you!<br>
							<span style="color: #007bff; font-weight: bold;">{{ config('app.name') }}</span>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
