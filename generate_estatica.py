from datetime import datetime
import os

filename = "/var/www/html/blacklist/blacklist_estatica_report.txt"
blacklist = []
with open(filename) as f:
    for line in f:
        blacklist.append(line.split(" "))

blacklist_uniq = []

for data in blacklist:
        date_time_str = data[0]
        val2 = datetime.strptime(date_time_str, '%Y%m%d%H%M%S')
        val1 = datetime.now()
        difference = val1 - val2
        HOUR_DIF = int(difference.total_seconds() / 60**2)
        if HOUR_DIF <= 2880 and not list(filter(lambda x: data[1] in x, blacklist_uniq)):
                blacklist_uniq.append(data[1])


blacklist_end = sorted(blacklist_uniq, key = lambda ip: [int(ip) for ip in ip.split(".")] )

blacklist_file = open("/var/www/html/blacklist_estatica.txt", "w")
for data in blacklist_end:
        blacklist_file.write(data)
blacklist_file.close()


os.chown("/var/www/html/blacklist_estatica.txt", 48, 48)
