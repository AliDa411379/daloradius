#!/bin/bash

DB_USER="bassel"
DB_PASS="bassel_password"
DB_NAME="radius"
DB_HOST="172.30.16.200"
LNS_IP="10.150.50.2"
COA_SECRET="sama@123"
REALM="samawifi.sy"
#a استعلام المستخدمين الذين لديهم سرعة أصلية (وليسوا ضمن مجموعة _2x)
mysql -N -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
SELECT username, groupname
FROM radusergroup
WHERE groupname LIKE 'speed_%' AND groupname NOT LIKE '%_2x'
" | while read username groupname; do

    double_group="${groupname}_2x"

    #a التحقق من أن مجموعة _2x موجودة
    group_exists=$(mysql -N -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
        SELECT 1 FROM radgroupreply WHERE groupname = '$double_group' LIMIT 1;
    ")

    if [ "$group_exists" == "1" ]; then

        #a التحقق من أن المستخدم غير مضاف مسبقًا للمجموعة المضاعفة
        already_added=$(mysql -N -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
            SELECT 1 FROM radusergroup WHERE username = '$username' AND groupname = '$double_group' LIMIT 1;
        ")

        if [ "$already_added" == "1" ]; then
            echo "⏩ $username is already in $double_group. Skipping."
        else
            echo "✅ Adding $username to $double_group"

            #a إدراج المستخدم للمجموعة الجديدة
            mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
                INSERT INTO radusergroup (username, groupname, priority)
                VALUES ('$username', '$double_group', 0);
            "

            #a جلب القيمة الحقيقية لـ Mikrotik-Address-List من مجموعة الرد
            address_list=$(mysql -N -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
                SELECT value FROM radgroupreply 
                WHERE groupname = '$double_group' AND attribute = 'Mikrotik-Address-List' 
                LIMIT 1;
            ")

            if [ -n "$address_list" ]; then

                #a التحقق مما إذا كان المستخدم متصلاً حالياً
                is_online=$(mysql -N -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" -e "
                    SELECT COUNT(*) FROM radacct WHERE username = '$username' AND acctstoptime IS NULL;
                ")

                if [ "$is_online" -ge 1 ]; then
                    #a إرسال CoA باستخدام القيمة وليس اسم المجموعة
                    echo "User-Name = '$username@$REALM', Mikrotik-Address-List := '$address_list'" | \
                    radclient -x $LNS_IP:3799 coa "$COA_SECRET"

                    echo "🚀 CoA sent to $username with Mikrotik-Address-List = $address_list"
                else
                    echo "ℹ️ $username is not online. CoA not sent."
                fi

            else
                echo "⚠️ No Mikrotik-Address-List found for $double_group. Skipping CoA."
            fi
        fi

    else
        echo "❌ Group $double_group does not exist in radgroupreply. Skipping $username."
    fi

done
