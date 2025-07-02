#產生QR
#按欄位qr對應的欄位E-class Key的名稱重新命名 再按E-class Key的名稱獨立各自建立file
#利用以上excel 欄位，用python讀取以上欄位，按照欄位QR生成png，然後再按欄位qr對應的欄位E-class Key重新命名再各自建立file

#[E-class Key Key Class Number Ename Cname *QR]
#QR = excel random 英文 大小寫5位 -> 
#=CHAR(RANDBETWEEN(65,90)) & CHAR(RANDBETWEEN(97,122)) & CHAR(RANDBETWEEN(65,90)) & CHAR(RANDBETWEEN(97,122)) & CHAR(RANDBETWEEN(65,90))

import pandas as pd
import qrcode
import os

# 讀取Excel文件
file_path = 'your_file.xlsx'  # 替換為Excel檔案路徑
df = pd.read_excel(file_path)

# 遍歷每一行
for index, row in df.iterrows():
    qr_data = row['QR']  # 取得QR列的數據
    e_class_key = row['E-class Key']  # 取得E-class Key列的數據

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