# coding=utf-8
import os
import re
import zipfile

replaceFile = [
    "./public/install/install_6.0.sh",
    "./public/install/update_panel.sh",
    "./public/install/update6.sh",
    "./public/win/install/panel_update.py",
    "./public/win/panel/data/setup.py",
    "./public/win/panel/data/api.py",
]

replaceZipDir = [
    "./public/install/src/",
    "./public/install/update/",
    "./public/win/panel/",
]

originalDomain = "www.example.com"

originalDomainSSL = {
    "http://www.example.com": "https://",
    "http:\/\/www.example.com": "https:\/\/",
}

isSSL = False


def replaceStringInFile(filePath, newString):
    with open(filePath, "r+", encoding="utf-8") as f:
        fileContent = f.read()
        if isSSL:
            for key, value in originalDomainSSL.items():
                fileContent = fileContent.replace(key, value+newString)
        else:
            fileContent = fileContent.replace(originalDomain, newString)
        f.seek(0)
        f.write(fileContent)
        f.truncate()


def replaceStringInZip(zipPath, newString):
    if zipPath.endswith(".zip"):
        oldZipPath = zipPath + ".old"
        os.rename(zipPath, oldZipPath)
        with zipfile.ZipFile(oldZipPath) as inZip, zipfile.ZipFile(zipPath, "w") as outZip:
            for inZipInfo in inZip.infolist():
                with inZip.open(inZipInfo) as inFile:
                    if (inZipInfo.filename.endswith(".py") or inZipInfo.filename.endswith(".sh")) and inZipInfo.filename != "panel/class/sewer/cli.py":
                        data = inFile.read()
                        if isSSL:
                            for key, value in originalDomainSSL.items():
                                data = data.replace(key.encode("utf-8"), (value + newString).encode("utf-8"))
                        else:
                            data = data.replace(originalDomain.encode("utf-8"), newString.encode("utf-8"))
                        outZip.writestr(inZipInfo, data)
                    else:
                        outZip.writestr(inZipInfo, inFile.read())
        os.remove(oldZipPath)


if __name__ == '__main__':
    newDomain = input("Please enter your domain(www.aaaa.com): ")
    if not re.match("^[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})*$", newDomain):
        print("Domain format error!")
        exit()
    print("Your domain is: " + newDomain)

    ssl = input("Is your domain SSL?(y/n): ")
    if ssl == "y":
        isSSL = True
    elif ssl == "n":
        isSSL = False
    else:
        print("Input error!")
        exit()

    projectPath = os.path.abspath(__file__+"/../..")
    print("Your project path is: " + projectPath)

    for aFilePath in replaceFile:
        aFileFullPath = os.path.abspath(projectPath+aFilePath)
        replaceStringInFile(aFileFullPath, newDomain)
    print("Single file replace done. wait for zip file replace...")

    for aZipDir in replaceZipDir:
        if os.path.exists(aZipDir):
            for aZipFile in os.listdir(aZipDir):
                if aZipFile.endswith(".zip"):
                    print(os.path.abspath(projectPath+aZipDir+aZipFile))
                    replaceStringInZip(aZipDir+aZipFile, newDomain)
    print("All done.")
