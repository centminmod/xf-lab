from pathlib import Path
import subprocess
lab=Path(__file__).resolve().parents[2] / 'instances/wmtech-1551'
b=['docker','compose','-p','xf_wmtech_1551','-f',str(lab/'compose.yml'),'exec','-T','php83']
subprocess.run(b+['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout','/tmp/xf-lab-tls.key','-out','/tmp/xf-lab-tls.crt','-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost,IP:127.0.0.1'],check=True,capture_output=True)
vhost='''<VirtualHost *:443>
ServerName localhost
DocumentRoot /var/www/html
SSLEngine on
SSLCertificateFile /tmp/xf-lab-tls.crt
SSLCertificateKeyFile /tmp/xf-lab-tls.key
<Directory /var/www/html>
Require all granted
AllowOverride All
</Directory>
</VirtualHost>
'''
subprocess.run(b+['tee','/etc/apache2/sites-available/xf-lab-ssl.conf'],input=vhost,text=True,check=True,capture_output=True)
for cmd in [['a2ensite','xf-lab-ssl'],['apache2ctl','-k','graceful']]:subprocess.run(b+cmd,check=True,capture_output=True)
crt=subprocess.run(b+['cat','/tmp/xf-lab-tls.crt'],check=True,capture_output=True).stdout
(lab/'evidence/tls-lab-cert.pem').write_bytes(crt)
print('Loopback TLS enabled; public test certificate saved; private key remains inside container.')
