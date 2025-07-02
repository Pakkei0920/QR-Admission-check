回應中顯示中英文名字、班別、學號等資訊，並且這些欄位都存在於 sql的tickets 資料表，在入場成功或失敗的回覆中都能顯示這些資訊；

生成random key -> python jen QR Code -> E-Class

synology http sever/xampp:
MYSQL -> 匯入範本graduation.sql/xxxx.sql
按照#qr.csv調整數據位置 -> 利用#input.php輸入資料到sql
local /ip.index.php (test)

-----------------------------------------------------------------------------------------------------------------------------------------------

# 按欄位qr對應的欄位E-class Key的名稱重新命名 再按E-class Key的名稱獨立各自建立file

import pandas as pd
import qrcode
import os

# 讀取Excel文件
file_path = 'your_file.xlsx'  # 替换为你的Excel文件路径
df = pd.read_excel(file_path)

# 遍歷每一行
for index, row in df.iterrows():
    qr_data = row['QR']  # 获取QR列的数据
    e_class_key = row['E-class Key']  # 获取E-class Key列的数据

    # 建立以E-class Key命名的目錄
    e_class_dir = os.path.join('output_images', str(e_class_key))
    os.makedirs(e_class_dir, exist_ok=True)

    # 產生二維碼
    qr = qrcode.make(qr_data)
    
    # 定義檔名
    file_name = f"{e_class_key}.png"
    file_path = os.path.join(e_class_dir, file_name)

    # 儲存二維碼圖像
    qr.save(file_path)

print(f"產生的二維碼影像保存在 'output_images' 目錄下的各自資料夾中。")
